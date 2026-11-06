<?php

namespace App\Services;

use App\Enums\AppPlatform;
use App\Enums\AppUpdateStatus;
use App\Models\AppRelease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AppUpdates
{
    public function check(AppPlatform $platform, int $build): array
    {
        $latest = $platform === AppPlatform::Android
            ? $this->githubAndroidRelease() ?? $this->databaseLatest($platform)
            : $this->databaseLatest($platform);
        $minimum = AppRelease::query()->published($platform)->where('required', true)->max('build');
        $minimum = $minimum === null ? null : (int) $minimum;

        $status = match (true) {
            $latest === null || $build >= $latest->build => AppUpdateStatus::UpToDate,
            $minimum !== null && $build < $minimum => AppUpdateStatus::UpdateRequired,
            default => AppUpdateStatus::UpdateAvailable,
        };

        return [
            'status' => $status,
            'latest' => $latest,
            'minimum_build' => $minimum,
            'update_url' => $this->updateUrl($platform),
        ];
    }

    private function databaseLatest(AppPlatform $platform): ?AppRelease
    {
        return AppRelease::query()->published($platform)->orderByDesc('build')->first();
    }

    private function githubAndroidRelease(): ?AppRelease
    {
        $release = Cache::remember('app-updates.github.android.latest', now()->addMinutes(5), function () {
            $repository = (string) config('services.app_updates.github_repository');
            if ($repository === '') return null;
            try {
                $response = Http::acceptJson()->timeout(8)->get('https://api.github.com/repos/'.$repository.'/releases', ['per_page' => 50]);
                if (! $response->successful()) return null;

                return collect($response->json())
                    ->filter(fn ($item) => is_array($item)
                        && ! ($item['draft'] ?? true)
                        && ! ($item['prerelease'] ?? true)
                        && preg_match('/^android-(\d+)$/', (string) ($item['tag_name'] ?? '')))
                    ->sortByDesc(fn ($item) => (int) preg_replace('/^android-/', '', (string) $item['tag_name']))
                    ->first();
            } catch (\Throwable) {
                return null;
            }
        });

        if (! is_array($release) || ! preg_match('/^android-(\d+)$/', (string) ($release['tag_name'] ?? ''), $match)) return null;

        $build = (int) $match[1];
        preg_match('/\bv?(\d+\.\d+\.\d+)\b/', (string) ($release['name'] ?? ''), $versionMatch);
        $assets = collect($release['assets'] ?? []);
        $apk = $assets->first(fn ($asset) => str_ends_with(strtolower((string) ($asset['name'] ?? '')), '.apk'));
        $checksum = $assets->first(fn ($asset) => str_ends_with(strtolower((string) ($asset['name'] ?? '')), '.apk.sha256'));
        $sha256 = null;

        if (is_array($checksum) && ! empty($checksum['browser_download_url'])) {
            try {
                $response = Http::timeout(8)->get($checksum['browser_download_url']);
                if ($response->successful() && preg_match('/\b([a-f0-9]{64})\b/i', $response->body(), $hash)) {
                    $sha256 = strtolower($hash[1]);
                }
            } catch (\Throwable) {
                // No checksum: the client will use the install page instead.
            }
        }

        return new AppRelease([
            'platform' => AppPlatform::Android,
            'version' => $versionMatch[1] ?? '0.0.0',
            'build' => $build,
            'required' => false,
            'notes' => trim((string) ($release['body'] ?? '')) ?: null,
            'download_url' => is_array($apk) ? ($apk['browser_download_url'] ?? null) : null,
            'sha256' => $sha256,
            'published_at' => Carbon::parse($release['published_at'] ?? $release['created_at'] ?? now()),
        ]);
    }

    public function updateUrl(AppPlatform $platform): ?string
    {
        $url = config('services.app_updates.'.$platform->value.'_url');
        return is_string($url) && $url !== '' ? $url : null;
    }
}
