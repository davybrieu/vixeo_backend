<?php

namespace App\Services;

use App\Enums\AppPlatform;
use App\Enums\AppUpdateStatus;
use App\Models\AppRelease;

/**
 * Tells an installed app whether to update (docs/mise-a-jour.md).
 *
 * Builds are compared, never version names. The newest release is the highest build among the
 * `app_releases` table (`php artisan vixeo:release`) and, for Android, the GitHub releases
 * published by the CI (GitHubReleases). A release marked `required` (table only) forces every
 * older install to update.
 */
class AppUpdates
{
    public function __construct(private GitHubReleases $github) {}

    /** @return array{status: AppUpdateStatus, latest: ?AppRelease, minimum_build: ?int, update_url: ?string} */
    public function check(AppPlatform $platform, int $build): array
    {
        $candidates = array_filter([
            AppRelease::query()->published($platform)->orderByDesc('build')->first(),
            $platform === AppPlatform::Android ? $this->github->latestAndroid() : null,
        ]);
        usort($candidates, fn (AppRelease $a, AppRelease $b) => $b->build <=> $a->build);
        $latest = $candidates[0] ?? null;

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

    /** Where people get the new version: the site's install page (no Play Store). */
    public function updateUrl(AppPlatform $platform): ?string
    {
        $url = config("services.app_updates.{$platform->value}_url");

        return is_string($url) && $url !== '' ? $url : null;
    }
}
