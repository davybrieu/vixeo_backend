<?php

namespace App\Services\Addons;

use App\Enums\MediaType;
use App\Models\Addon;
use App\Models\AddonPreference;
use App\Models\Profile;
use App\Models\User;
use App\Services\Playback\DeadStreams;
use App\Services\Playback\PlaybackServiceUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The plug-ins of an account (docs/plugins.md), the only place that reads and writes them:
 *
 * - the Vixeo catalog first, built in ([VixeoCatalogAddon]), then those installed for every
 *   account (`user_id` null), then the account's own, each in their order;
 * - their catalogs as rows of the home (`sections`) and "see all" pages (`catalogPage`);
 * - their streams as providers of the sources panel (`streamProviders`, `streams`).
 *
 * A plug-in that fails never breaks a page: its rows are left out, its provider says it is
 * unavailable.
 */
class Addons
{
    /** Provider ids of the sources panel: "addon:12". */
    public const PROVIDER_PREFIX = 'addon:';

    /** List names of a plug-in catalog: "addon:12:top" (route `/catalog/{type}/lists/{list}`). */
    public const LIST_PREFIX = 'addon:';

    public function __construct(
        private AddonHttp $http,
        private VixeoCatalogAddon $vixeo,
        private TitleIds $ids,
        private DeadStreams $dead,
    ) {}

    // ---------------------------------------------------------------------------------------
    // Installed plug-ins

    /**
     * Every plug-in an account sees, the Vixeo catalog first, with whether it is on for this
     * account (`enabled`): its own choice for the Vixeo catalog and the plug-ins of everyone
     * (`addon_preferences`), the plug-in's switch for its own ones.
     *
     * @return list<array{key: string, manifest: AddonManifest, addon: Addon|null, enabled: bool}>
     */
    public function installed(?User $user, bool $enabledOnly = false): array
    {
        $preferences = $this->preferences($user);
        $list = [[
            'key' => VixeoCatalogAddon::KEY,
            'manifest' => $this->vixeo->parsedManifest(),
            'addon' => null,
            'enabled' => $preferences[VixeoCatalogAddon::KEY] ?? true,
        ]];

        foreach (Addon::query()->visibleTo($user)->get() as $addon) {
            try {
                $list[] = [
                    'key' => (string) $addon->id,
                    'manifest' => $addon->parsedManifest(),
                    'addon' => $addon,
                    'enabled' => $this->isEnabled($addon, $preferences),
                ];
            } catch (InvalidAddon) {
                // A manifest stored by an older version that no longer reads: left out.
            }
        }

        return $enabledOnly ? array_values(array_filter($list, fn (array $entry) => $entry['enabled'])) : $list;
    }

    /** Whether the Vixeo catalog is on for the account (always for a guest). */
    public function vixeoEnabled(?User $user): bool
    {
        return $this->preferences($user)[VixeoCatalogAddon::KEY] ?? true;
    }

    /**
     * Turns a plug-in on or off for the account: the Vixeo catalog and the plug-ins of everyone
     * through its preferences (the others keep theirs), its own ones through their switch.
     *
     * @return array{key: string, manifest: AddonManifest, addon: Addon|null, enabled: bool}
     */
    public function setEnabled(User $user, string $key, bool $enabled): array
    {
        $shared = ctype_digit($key) ? Addon::query()->whereNull('user_id')->find((int) $key) : null;

        if ($key === VixeoCatalogAddon::KEY || $shared !== null) {
            AddonPreference::updateOrCreate(['user_id' => $user->id, 'addon_key' => $key], ['enabled' => $enabled]);
        } else {
            $this->owned($user, $key)->update(['enabled' => $enabled]);
        }

        foreach ($this->installed($user) as $entry) {
            if ($entry['key'] === $key) {
                return $entry;
            }
        }

        throw new NotFoundHttpException(__('This plug-in is not installed.'));
    }

    /** @return array<string, bool> addon key => on */
    private function preferences(?User $user): array
    {
        return $user === null ? [] : AddonPreference::query()->where('user_id', $user->id)
            ->pluck('enabled', 'addon_key')->map(fn ($enabled) => (bool) $enabled)->all();
    }

    /** @param  array<string, bool>  $preferences */
    private function isEnabled(Addon $addon, array $preferences): bool
    {
        return $addon->enabled && ($addon->user_id !== null || ($preferences[(string) $addon->id] ?? true));
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
        if ($manifest->id === VixeoCatalogAddon::ID) {
            throw new InvalidAddon(__('This plug-in is already installed.'));
        }
        if ($manifest->configurationRequired) {
            throw new InvalidAddon(__('This plug-in must be configured on its website first: paste the address it gives you then.'));
        }
        if (! $manifest->isUseful()) {
            throw new InvalidAddon(__('This plug-in offers no catalog nor streams of movies or TV shows.'));
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

    /** An account's own plug-in (those of every account and the Vixeo catalog are not its to change). */
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
     * Reads again the manifests not read for a day (name, catalogs, version may change). Quietly:
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
    // Catalogs

    /**
     * Rows of the home from the catalogs of the account's plug-ins (not the Vixeo catalog, whose
     * rows are the home itself): `{key, title, type, list, items, light}`, items as TMDb titles.
     *
     * @return list<array{key: string, title: string, type: string, list: string, items: list<array<string, mixed>>, light: bool}>
     */
    public function sections(?User $user, ?Profile $profile): array
    {
        $rows = [];
        $urls = [];
        foreach ($this->installed($user, enabledOnly: true) as $entry) {
            $addon = $entry['addon'];
            $manifest = $entry['manifest'];
            if ($addon === null || ($manifest->adult && $profile?->isRestricted())) {
                continue;
            }
            $catalogs = [...$manifest->catalogsFor('movie'), ...$manifest->catalogsFor('series')];
            foreach (array_slice($catalogs, 0, (int) config('services.addons.home_catalogs', 6)) as $catalog) {
                $type = AddonManifest::mediaType($catalog['type']);
                $key = "{$addon->id}:{$catalog['type']}:{$catalog['id']}";
                $urls[$key] = $this->resourceUrl($addon, 'catalog', $catalog['type'], $catalog['id']);
                $rows[$key] = [
                    'key' => "{$type->value}_".self::LIST_PREFIX."{$addon->id}:{$catalog['id']}",
                    'title' => "{$catalog['name']} · {$manifest->name}",
                    'type' => $type->value,
                    'list' => self::LIST_PREFIX."{$addon->id}:{$catalog['id']}",
                    'light' => true,
                    'stremio_type' => $catalog['type'],
                ];
            }
        }
        if ($rows === []) {
            return [];
        }

        $answers = $this->fetchCached($urls, 'catalog');
        $refs = [];
        foreach ($rows as $key => $row) {
            foreach (self::metaIds($answers[$key] ?? null) as $index => $id) {
                $refs["{$key}#{$index}"] = [MediaType::from($row['type']), $id];
            }
        }
        $items = $this->ids->items($refs);

        $sections = [];
        foreach ($rows as $key => $row) {
            $rowItems = [];
            foreach ($items as $ref => $item) {
                if ($item !== null && str_starts_with($ref, "{$key}#")) {
                    $rowItems[] = $item;
                }
            }
            unset($row['stremio_type']);
            if ($rowItems !== []) {
                $sections[] = ['items' => self::unique($rowItems)] + $row;
            }
        }

        return $sections;
    }

    /**
     * A page of a plug-in catalog ("see all"), in the shape of TmdbCatalog pages. Pages after the
     * first only when the catalog supports `skip`; their size is that of the first page.
     *
     * @return array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>}
     */
    public function catalogPage(?User $user, MediaType $type, string $list, int $page): array
    {
        [$addon, $catalogId] = $this->listTarget($user, $list);
        $stremioType = AddonManifest::stremioType($type);
        $catalog = $addon->parsedManifest()->catalog($stremioType, $catalogId);
        if ($catalog === null) {
            throw new NotFoundHttpException;
        }

        $firstUrl = $this->resourceUrl($addon, 'catalog', $stremioType, $catalogId);
        $first = $this->fetchCached(['first' => $firstUrl], 'catalog')['first'];
        $pageSize = count(self::metaIds($first));

        $answer = $first;
        if ($page > 1) {
            $answer = $catalog['skip'] && $pageSize > 0
                ? $this->fetchCached(['page' => $this->resourceUrl($addon, 'catalog', $stremioType, $catalogId, ['skip' => (string) (($page - 1) * $pageSize)])], 'catalog')['page']
                : null;
        }

        $ids = self::metaIds($answer);
        $results = array_values(array_filter($this->ids->items(array_map(fn (string $id) => [$type, $id], $ids))));
        $more = $catalog['skip'] && $pageSize > 0 && count($ids) >= $pageSize;

        return [
            'page' => $page,
            'total_pages' => $more ? $page + 1 : $page,
            'total_results' => ($page - 1) * $pageSize + count($ids),
            'results' => self::unique($results),
        ];
    }

    public static function isAddonList(string $list): bool
    {
        return str_starts_with($list, self::LIST_PREFIX);
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
            if ($entry['addon'] !== null && ($manifest->supports('stream', 'movie') || $manifest->supports('stream', 'series'))) {
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

    /** A plug-in the account sees (its own or everyone's) and has on, by its id. */
    private function visible(?User $user, string $key): Addon
    {
        $addon = ctype_digit($key) ? Addon::query()->visibleTo($user)->whereKey((int) $key)->first() : null;
        if ($addon === null || ! $this->isEnabled($addon, $this->preferences($user))) {
            throw new NotFoundHttpException(__('This plug-in is not installed.'));
        }

        return $addon;
    }

    /** @return array{Addon, string} */
    private function listTarget(?User $user, string $list): array
    {
        $parts = explode(':', substr($list, strlen(self::LIST_PREFIX)), 2);
        if (count($parts) !== 2 || $parts[1] === '') {
            throw new NotFoundHttpException;
        }

        return [$this->visible($user, $parts[0]), $parts[1]];
    }

    /**
     * `{base}/{resource}/{type}/{id}[/{extra}].json`. Ids keep their colons (`tt0944947:1:2`), as
     * Stremio sends them.
     *
     * @param  array<string, string>  $extra
     */
    private function resourceUrl(Addon $addon, string $resource, string $type, string $id, array $extra = []): string
    {
        $encode = fn (string $part) => str_replace('%3A', ':', rawurlencode($part));
        $path = $addon->baseUrl()."/{$resource}/".$encode($type).'/'.$encode($id);
        if ($extra !== []) {
            $path .= '/'.implode('&', array_map(fn ($k, $v) => rawurlencode($k).'='.rawurlencode($v), array_keys($extra), $extra));
        }

        return $path.'.json';
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

    /**
     * Ids of a catalog answer (`{metas: [{id}]}`), in order.
     *
     * @param  array<mixed>|null  $answer
     * @return list<string>
     */
    private static function metaIds(?array $answer): array
    {
        $ids = [];
        foreach (is_array($answer['metas'] ?? null) ? $answer['metas'] : [] as $meta) {
            if (is_array($meta) && is_string($meta['id'] ?? null) && $meta['id'] !== '') {
                $ids[] = $meta['id'];
            }
        }

        return array_slice($ids, 0, 100);
    }

    /**
     * Titles once each (two ids of a plug-in may be the same TMDb title).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function unique(array $items): array
    {
        $seen = [];

        return array_values(array_filter($items, function (array $item) use (&$seen) {
            $key = ($item['media_type'] ?? '').':'.($item['id'] ?? '');

            return isset($seen[$key]) ? false : ($seen[$key] = true);
        }));
    }
}
