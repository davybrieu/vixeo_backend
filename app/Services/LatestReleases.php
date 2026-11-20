<?php

namespace App\Services;

use App\Enums\AppPlatform;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Latest published Android and Windows versions (APK, installer), shown by the presentation site.
 * Same source as `GET /api/v1/app/update`: the GitHub releases of the vixeo_frontend CI
 * (GitHubReleases, cached). Any failure hides the version: a site page never breaks because of it.
 */
class LatestReleases
{
    /** @var array<string, array{version: string, notes: ?string, publishedAt: ?string, downloadUrl: ?string, sha256: ?string}|null> */
    private array $releases = [];

    public function __construct(private GitHubReleases $github) {}

    /**
     * The APK to offer: the latest release's file, or the one set in the .env.
     *
     * @return array{url: string, sha256: ?string}|null
     */
    public function apk(): ?array
    {
        $release = $this->get(AppPlatform::Android);
        if ($release !== null && $release['downloadUrl'] !== null) {
            return ['url' => $release['downloadUrl'], 'sha256' => $release['sha256']];
        }

        $url = config('vixeo.android.apk_url');

        return is_string($url) && $url !== ''
            ? ['url' => $url, 'sha256' => config('vixeo.android.apk_sha256') ?: null]
            : null;
    }

    /**
     * The Windows installer to offer: the latest release's file, or none.
     *
     * @return array{url: string, sha256: ?string}|null
     */
    public function windowsInstaller(): ?array
    {
        $release = $this->get(AppPlatform::Windows);

        return $release !== null && $release['downloadUrl'] !== null
            ? ['url' => $release['downloadUrl'], 'sha256' => $release['sha256']]
            : null;
    }

    /** @return array{version: string, notes: ?string, publishedAt: ?string, downloadUrl: ?string, sha256: ?string}|null */
    public function get(AppPlatform $platform): ?array
    {
        if (array_key_exists($platform->value, $this->releases)) {
            return $this->releases[$platform->value];
        }

        try {
            $latest = $this->github->for($platform)['latest'];
        } catch (Throwable $e) {
            // Cache (Redis) down, unexpected payload…: the page is shown without the version.
            Log::warning('Latest release unavailable for the site', ['platform' => $platform->value, 'error' => $e->getMessage()]);
            $latest = null;
        }

        return $this->releases[$platform->value] = $latest === null ? null : [
            'version' => $latest->version,
            'notes' => $latest->notes,
            'publishedAt' => $latest->publishedAt->toIso8601String(),
            // GitHubReleases only keeps https links.
            'downloadUrl' => $latest->downloadUrl,
            'sha256' => $latest->sha256,
        ];
    }
}
