<?php

namespace App\Services\Addons;

use App\Enums\MediaType;
use App\Models\Addon;
use App\Models\User;
use App\Services\Playback\DeadStreams;
use App\Services\Playback\PlaybackServiceUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The plug-ins of an account (docs/plugins.md), the only place that reads and writes them.
 * Plug-ins only add **sources**: their streams are providers of the sources panel
 * (`streamProviders`, `streams`). The catalog (home, lists, details, search) is TMDb's and
 * plug-ins never change it.
 *
 * Order: those installed for every account (`user_id` null), then the account's own. A plug-in
 * that fails never breaks a page: its provider says it is unavailable.
 */
class Addons
{
    /** Provider ids of the sources panel: "addon:12". */
    public const PROVIDER_PREFIX = 'addon:';

    public function __construct(
        private AddonHttp $http,
        private TitleIds $ids,
        private DeadStreams $dead,
    ) {}

    // ---------------------------------------------------------------------------------------
    // Installed plug-ins

    /**
     * Every plug-in an account sees: those of every account, then its own.
     *
     * @return list<array{key: string, manifest: AddonManifest, addon: Addon}>
     */
    public function installed(?User $user, bool $enabledOnly = false): array
    {
        $list = [];

        $addons = Addon::query()->visibleTo($user)->when($enabledOnly, fn ($q) => $q->where('enabled', true))->get();
        foreach ($addons as $addon) {
            try {
                $list[] = ['key' => (string) $addon->id, 'manifest' => $addon->parsedManifest(), 'addon' => $addon];
            } catch (InvalidAddon) {
                // A manifest stored by an older version that no longer reads: left out.
            }
        }

        return $list;
    }

    /**
     * Installs a plug-in from the address of its manifest (`…/manifest.json`, `stremio://…`).
     * Throws InvalidAddon with a French message.
     */
    public function install(?User $user, string $input): Addon
    {
        $url = AddonManifest::normalizeUrl($input);
        if (strlen($url) > 2048) {
            throw new InvalidAddon(__('This plug-in address is too long.'));
        }
        if ($user !== null && $user->addons()->count() >= (int) config('services.addons.max_per_user', 30)) {
            throw new InvalidAddon(__('You have reached the maximum number of plug-ins.'));
        }

        $hash = Addon::hashUrl($url);
        $taken = Addon::query()->where('url_hash', $hash)
            ->where(fn ($q) => $q->whereNull('user_id')->when($user !== null, fn ($q) => $q->orWhere('user_id', $user->id)))
            ->exists();
        if ($taken) {
            throw new InvalidAddon(__('This plug-in is already installed.'));
        }

        $manifest = AddonManifest::fromArray($this->http->getJson($url));
        if ($manifest->configurationRequired) {
            throw new InvalidAddon(__('This plug-in must be configured on its website first: paste the address it gives you then.'));
        }
        if (! $manifest->servesStreams()) {
            throw new InvalidAddon(__('This plug-in offers no sources for movies or TV shows.'));
        }

        return DB::transaction(function () use ($user, $url, $hash, $manifest) {
            $position = (int) Addon::query()->where('user_id', $user?->id)->max('position') + 1;

            return Addon::create([
                'user_id' => $user?->id,
                'transport_url' => $url,
                'url_hash' => $hash,
                'addon_id' => $manifest->id,
                'manifest' => $manifest->raw,
                'position' => $position,
                'enabled' => true,
                'manifest_checked_at' => now(),
            ]);
        });
    }

    /** An account's own plug-in (those of every account are not its to change). */
    public function owned(User $user, string $key): Addon
    {
        $addon = ctype_digit($key) ? $user->addons()->whereKey((int) $key)->first() : null;
        if ($addon === null) {
            throw new NotFoundHttpException(__('This plug-in is not installed.'));
        }

        return $addon;
    }

    /**
     * New order of an account's own plug-ins (ids, first first). Ids that are not its own are ignored.
     *
     * @param  list<string>  $keys
     */
    public function reorder(User $user, array $keys): void
    {
        DB::transaction(function () use ($user, $keys) {
            $position = 1;
            foreach ($keys as $key) {
                if (ctype_digit($key)) {
                    $user->addons()->whereKey((int) $key)->update(['position' => $position++]);
                }
            }
        });
    }

    /**
     * Reads again the manifests not read for a day (name, version, ids may change). Quietly:
     * a plug-in that does not answer keeps its manifest.
     */
    public function refreshStale(?User $user): void
    {
        $stale = Addon::query()->visibleTo($user)
            ->where(fn ($q) => $q->whereNull('manifest_checked_at')->orWhere('manifest_checked_at', '<', now()->subDay()))
            ->limit(10)->get();
        if ($stale->isEmpty()) {
            return;
        }

        $answers = $this->http->getManyJson($stale->mapWithKeys(fn (Addon $a) => [(string) $a->id => $a->transport_url])->all());
        foreach ($stale as $addon) {
            $json = $answers[(string) $addon->id] ?? null;
            try {
                $manifest = $json === null ? null : AddonManifest::fromArray($json);
            } catch (InvalidAddon) {
                $manifest = null;
            }
            $addon->forceFill(['manifest_checked_at' => now()] + ($manifest === null || $manifest->id !== $addon->addon_id
                ? []
                : ['manifest' => $manifest->raw]))->save();
        }
    }

    // ---------------------------------------------------------------------------------------
    // Streams

    /**
     * Providers of the sources panel from the plug-ins that serve streams of movies or series.
     *
     * @return list<array{id: string, name: string}>
     */
    public function streamProviders(?User $user): array
    {
        $providers = [];
        foreach ($this->installed($user, enabledOnly: true) as $entry) {
            $manifest = $entry['manifest'];
            if ($manifest->servesStreams()) {
                $providers[] = ['id' => self::PROVIDER_PREFIX.$entry['key'], 'name' => $manifest->name];
            }
        }

        return $providers;
    }

    public static function isAddonProvider(?string $provider): bool
    {
        return $provider !== null && str_starts_with($provider, self::PROVIDER_PREFIX);
    }

    /**
     * The streams one plug-in gives for a movie or an episode, in the shape of the sources panel:
     * already playable (`stream`), with the headers to send. Torrents, YouTube videos and links
     * to open elsewhere are left out: Vixeo plays addresses.
     *
     * @return list<array<string, mixed>>
     */
    public function streams(?User $user, string $provider, MediaType $type, int $tmdbId, ?int $season = null, ?int $episode = null): array
    {
        $addon = $this->visible($user, substr($provider, strlen(self::PROVIDER_PREFIX)));
        $manifest = $addon->parsedManifest();
        $stremioType = AddonManifest::stremioType($type);

        $suffix = $type === MediaType::Tv ? ":{$season}:{$episode}" : '';
        $candidates = array_filter([
            ($imdb = $this->ids->imdbId($type, $tmdbId)) === null ? null : $imdb.$suffix,
            "tmdb:{$tmdbId}{$suffix}",
        ]);
        $id = null;
        foreach ($candidates as $candidate) {
            if ($manifest->supports('stream', $stremioType, $candidate)) {
                $id = $candidate;
                break;
            }
        }
        if ($id === null) {
            return [];
        }

        $answer = $this->fetchCached(['streams' => $this->resourceUrl($addon, 'stream', $stremioType, $id)], 'streams')['streams'];
        if ($answer === null) {
            throw new PlaybackServiceUnavailable(__('This plug-in is temporarily unavailable.'));
        }

        $sources = [];
        foreach (is_array($answer['streams'] ?? null) ? $answer['streams'] : [] as $stream) {
            $source = is_array($stream) ? $this->source($stream, $provider, $manifest) : null;
            if ($source !== null) {
                $sources[] = $source;
            }
        }

        return $this->dead->filter($sources);
    }

    /**
     * One Stremio stream as a source of the panel, or null when Vixeo cannot play it.
     *
     * @param  array<mixed>  $stream
     * @return array<string, mixed>|null
     */
    private function source(array $stream, string $provider, AddonManifest $manifest): ?array
    {
        $url = $stream['url'] ?? null;
        if (! is_string($url) || preg_match('#^https?://#i', $url) !== 1 || strlen($url) > 4096) {
            return null;
        }

        $hints = is_array($stream['behaviorHints'] ?? null) ? $stream['behaviorHints'] : [];
        $request = is_array($hints['proxyHeaders']['request'] ?? null) ? $hints['proxyHeaders']['request'] : [];
        $headers = [];
        foreach ($request as $name => $value) {
            if (is_string($name) && is_scalar($value) && preg_match('/^[A-Za-z0-9-]{1,64}$/', $name) === 1) {
                $headers[$name] = (string) $value;
            }
        }

        // "Name" then the details (quality, size…), on one line.
        $title = is_string($stream['title'] ?? null) ? $stream['title'] : (is_string($stream['description'] ?? null) ? $stream['description'] : '');
        $label = is_string($stream['name'] ?? null) && trim($stream['name']) !== '' ? trim($stream['name']) : $manifest->name;
        $details = trim(implode(' · ', array_filter(array_map('trim', preg_split('/\R/', $title) ?: []))));
        $name = mb_substr(preg_replace('/\s+/', ' ', $details === '' ? $label : "{$label} · {$details}") ?? $label, 0, 160);

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return [
            'provider' => $provider,
            'name' => $name,
            'url' => $url,
            'stream' => $url,
            'streamType' => match (true) {
                str_ends_with($path, '.m3u8') => 'm3u8',
                str_ends_with($path, '.mp4') => 'mp4',
                default => null,
            },
            'extractor' => null,
            'headers' => $headers,
        ];
    }

    // ---------------------------------------------------------------------------------------
    // Plumbing

    /** An enabled plug-in the account sees (its own or everyone's), by its id. */
    private function visible(?User $user, string $key): Addon
    {
        $addon = ctype_digit($key)
            ? Addon::query()->visibleTo($user)->where('enabled', true)->whereKey((int) $key)->first()
            : null;
        if ($addon === null) {
            throw new NotFoundHttpException(__('This plug-in is not installed.'));
        }

        return $addon;
    }

    /**
     * `{base}/{resource}/{type}/{id}.json`. Ids keep their colons (`tt0944947:1:2`), as Stremio
     * sends them.
     */
    private function resourceUrl(Addon $addon, string $resource, string $type, string $id): string
    {
        $encode = fn (string $part) => str_replace('%3A', ':', rawurlencode($part));

        return $addon->baseUrl()."/{$resource}/".$encode($type).'/'.$encode($id).'.json';
    }

    /**
     * Several plug-in answers, from the cache when they are there. Failures are not cached.
     *
     * @param  array<string, string>  $urls
     * @return array<string, array<mixed>|null>
     */
    private function fetchCached(array $urls, string $ttl): array
    {
        $answers = [];
        $missing = [];
        foreach ($urls as $key => $url) {
            $answers[$key] = Cache::get('addon:'.sha1($url));
            if ($answers[$key] === null) {
                $missing[$key] = $url;
            }
        }

        foreach ($this->http->getManyJson($missing) as $key => $json) {
            $answers[$key] = $json;
            if ($json !== null) {
                Cache::put('addon:'.sha1($missing[$key]), $json, (int) config("services.addons.ttl.{$ttl}", 600));
            } else {
                Log::info('Plug-in resource unavailable', ['host' => parse_url($missing[$key], PHP_URL_HOST)]);
            }
        }

        return $answers;
    }
}
