<?php

namespace App\Services;

use App\Enums\AppPlatform;
use App\Support\AppRelease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * App releases published on GitHub (repository `services.app_updates.github_repository`), the only
 * source of the update check (docs/mise-a-jour.md): the CI publishes each signed APK as a release
 * tagged `android-<build>` (the APK and its `.apk.sha256`), and each Windows installer as
 * `windows-<build>` (the `.exe` and its `.exe.sha256`). A release whose title contains
 * « [obligatoire] » forces older installs of its platform to update. Read without a token (public
 * repository), cached 5 minutes. Any failure gives no release: the apps are then up to date, never
 * blocked.
 */
class GitHubReleases
{
    private const CACHE_KEY = 'app-updates:github:%s:v3';

    private const TTL = 300;

    /** In the title of a GitHub release: every install older than its build must update. */
    public const REQUIRED_MARK = '[obligatoire]';

    /**
     * The newest Android release, and the build every install must reach (null: none required).
     *
     * @return array{latest: ?AppRelease, minimum_build: ?int}
     */
    public function android(): array
    {
        return $this->for(AppPlatform::Android);
    }

    /**
     * The newest release of [$platform] and the build every install must reach. Nothing for a
     * platform without releases (iOS).
     *
     * @return array{latest: ?AppRelease, minimum_build: ?int}
     */
    public function for(AppPlatform $platform): array
    {
        $repository = (string) config('services.app_updates.github_repository');
        $found = $repository === '' || $platform->fileExtension() === null
            ? null
            : Cache::remember(sprintf(self::CACHE_KEY, $platform->value), self::TTL, fn () => $this->fetch($repository, $platform) ?? false);

        return is_array($found)
            ? ['latest' => $this->toRelease($found['latest'], $platform), 'minimum_build' => $found['minimum_build']]
            : ['latest' => null, 'minimum_build' => null];
    }

    /**
     * Published (not draft, not pre-release) `<platform>-<build>` releases: the newest one with its
     * checksum, and the highest build marked « [obligatoire] ».
     *
     * @return array{latest: array<string, mixed>, minimum_build: ?int}|null
     */
    private function fetch(string $repository, AppPlatform $platform): ?array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(8)
                ->get("https://api.github.com/repos/{$repository}/releases", ['per_page' => 50]);
        } catch (Throwable $e) {
            Log::warning('GitHub releases unreachable', ['error' => $e->getMessage()]);

            return null;
        }
        if (! $response->successful() || ! is_array($response->json())) {
            Log::warning('GitHub releases request failed', ['status' => $response->status()]);

            return null;
        }

        $releases = collect($response->json())
            ->filter(fn ($item) => is_array($item)
                && ! ($item['draft'] ?? true)
                && ! ($item['prerelease'] ?? true)
                && self::buildOf($item, $platform) !== null)
            ->sortByDesc(fn (array $item) => self::buildOf($item, $platform));
        $latest = $releases->first();
        if ($latest === null) {
            return null;
        }
        $latest['sha256'] = $this->checksum(collect($latest['assets'] ?? []), $platform);
        $minimum = $releases->filter(fn (array $item) => self::isRequired($item))->map(fn (array $item) => self::buildOf($item, $platform))->max();

        return ['latest' => $latest, 'minimum_build' => $minimum === null ? null : (int) $minimum];
    }

    /** SHA-256 from the `.apk.sha256` / `.exe.sha256` asset, or null (the app then sends people to the site). */
    private function checksum(Collection $assets, AppPlatform $platform): ?string
    {
        $suffix = '.'.$platform->fileExtension().'.sha256';
        $asset = $assets->first(fn ($a) => is_array($a) && str_ends_with(strtolower((string) ($a['name'] ?? '')), $suffix));
        $url = is_array($asset) ? ($asset['browser_download_url'] ?? null) : null;
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            return null;
        }

        try {
            $response = Http::connectTimeout(5)->timeout(8)->get($url);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() && preg_match('/\b([a-f0-9]{64})\b/i', $response->body(), $hash)
            ? strtolower($hash[1])
            : null;
    }

    /** @param  array<string, mixed>  $release */
    private function toRelease(array $release, AppPlatform $platform): AppRelease
    {
        preg_match('/\bv?(\d+\.\d+\.\d+)\b/', (string) ($release['name'] ?? ''), $version);
        $suffix = '.'.$platform->fileExtension();
        $file = collect($release['assets'] ?? [])
            ->first(fn ($a) => is_array($a) && str_ends_with(strtolower((string) ($a['name'] ?? '')), $suffix));
        $url = is_array($file) ? ($file['browser_download_url'] ?? null) : null;
        $notes = trim((string) ($release['body'] ?? ''));

        return new AppRelease(
            version: $version[1] ?? '0.0.0',
            build: (int) self::buildOf($release, $platform),
            required: self::isRequired($release),
            notes: $notes === '' ? null : $notes,
            // The app only installs over https, with a checksum.
            downloadUrl: is_string($url) && str_starts_with($url, 'https://') ? $url : null,
            sha256: is_string($release['sha256'] ?? null) ? $release['sha256'] : null,
            publishedAt: Carbon::parse($release['published_at'] ?? $release['created_at'] ?? now()),
        );
    }

    /** @param  array<string, mixed>  $release */
    private static function buildOf(array $release, AppPlatform $platform): ?int
    {
        return preg_match('/^'.$platform->value.'-(\d+)$/', (string) ($release['tag_name'] ?? ''), $match) ? (int) $match[1] : null;
    }

    /** @param  array<string, mixed>  $release */
    private static function isRequired(array $release): bool
    {
        return str_contains(mb_strtolower((string) ($release['name'] ?? '')), self::REQUIRED_MARK);
    }
}
