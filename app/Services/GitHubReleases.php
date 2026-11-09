<?php

namespace App\Services;

use App\Enums\AppPlatform;
use App\Models\AppRelease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Android releases published on GitHub (repository `services.app_updates.github_repository`):
 * the CI publishes each signed APK as a release tagged `android-<build>`, with the APK and its
 * `.apk.sha256` as assets (docs/mise-a-jour.md). Read without a token (public repository), cached
 * 5 minutes. Any failure gives null: the update check then uses the `app_releases` table.
 */
class GitHubReleases
{
    private const CACHE_KEY = 'app-updates:github:android';

    private const TTL = 300;

    public function latestAndroid(): ?AppRelease
    {
        $repository = (string) config('services.app_updates.github_repository');
        if ($repository === '') {
            return null;
        }

        $release = Cache::remember(self::CACHE_KEY, self::TTL, fn () => $this->fetch($repository) ?? false);

        return is_array($release) ? $this->toRelease($release) : null;
    }

    /**
     * Newest published (not draft, not pre-release) `android-<build>` release, with its checksum.
     *
     * @return array<string, mixed>|null
     */
    private function fetch(string $repository): ?array
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

        $release = collect($response->json())
            ->filter(fn ($item) => is_array($item)
                && ! ($item['draft'] ?? true)
                && ! ($item['prerelease'] ?? true)
                && self::buildOf($item) !== null)
            ->sortByDesc(fn (array $item) => self::buildOf($item))
            ->first();
        if ($release === null) {
            return null;
        }

        $release['sha256'] = $this->checksum(collect($release['assets'] ?? []));

        return $release;
    }

    /** SHA-256 from the `.apk.sha256` asset, or null (the app then sends people to the site). */
    private function checksum(Collection $assets): ?string
    {
        $asset = $assets->first(fn ($a) => is_array($a) && str_ends_with(strtolower((string) ($a['name'] ?? '')), '.apk.sha256'));
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
    private function toRelease(array $release): AppRelease
    {
        preg_match('/\bv?(\d+\.\d+\.\d+)\b/', (string) ($release['name'] ?? ''), $version);
        $apk = collect($release['assets'] ?? [])
            ->first(fn ($a) => is_array($a) && str_ends_with(strtolower((string) ($a['name'] ?? '')), '.apk'));
        $url = is_array($apk) ? ($apk['browser_download_url'] ?? null) : null;
        $notes = trim((string) ($release['body'] ?? ''));

        return new AppRelease([
            'platform' => AppPlatform::Android,
            'version' => $version[1] ?? '0.0.0',
            'build' => (int) self::buildOf($release),
            'required' => false,
            'notes' => $notes === '' ? null : $notes,
            // The app only installs over https, with a checksum.
            'download_url' => is_string($url) && str_starts_with($url, 'https://') ? $url : null,
            'sha256' => is_string($release['sha256'] ?? null) ? $release['sha256'] : null,
            'published_at' => Carbon::parse($release['published_at'] ?? $release['created_at'] ?? now()),
        ]);
    }

    /** @param  array<string, mixed>  $release */
    private static function buildOf(array $release): ?int
    {
        return preg_match('/^android-(\d+)$/', (string) ($release['tag_name'] ?? ''), $match) ? (int) $match[1] : null;
    }
}
