<?php

namespace App\Services;

use App\Enums\AppPlatform;
use App\Enums\AppUpdateStatus;
use App\Models\AppRelease;

/**
 * Tells an installed app whether to update (docs/mise-a-jour.md).
 *
 * Builds are compared, never version names: the build number grows with every release sent to
 * the stores. A release marked `required` forces every older install to update.
 */
class AppUpdates
{
    /** @return array{status: AppUpdateStatus, latest: ?AppRelease, minimum_build: ?int, update_url: ?string} */
    public function check(AppPlatform $platform, int $build): array
    {
        $latest = AppRelease::query()->published($platform)->orderByDesc('build')->first();
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

    /** Where people get the new version: the Play Store page, the website's download page… */
    public function updateUrl(AppPlatform $platform): ?string
    {
        $url = config("services.app_updates.{$platform->value}_url");

        return is_string($url) && $url !== '' ? $url : null;
    }
}
