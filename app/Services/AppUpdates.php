<?php

namespace App\Services;

use App\Enums\AppPlatform;
use App\Enums\AppUpdateStatus;
use App\Support\AppRelease;

/**
 * Tells an installed app whether to update (docs/mise-a-jour.md).
 *
 * Builds are compared, never version names. The only source is GitHub: the Android releases
 * published by the CI (GitHubReleases); one whose title contains « [obligatoire] » forces every
 * older install to update. No iOS app is published yet: iOS is always up to date.
 */
class AppUpdates
{
    public function __construct(private GitHubReleases $github) {}

    /** @return array{status: AppUpdateStatus, latest: ?AppRelease, minimum_build: ?int, update_url: ?string} */
    public function check(AppPlatform $platform, int $build): array
    {
        ['latest' => $latest, 'minimum_build' => $minimum] = $platform === AppPlatform::Android
            ? $this->github->android()
            : ['latest' => null, 'minimum_build' => null];

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
