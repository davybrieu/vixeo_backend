<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Latest published Android version (and its APK), shown by the presentation site. Same source as
 * `GET /api/v1/app/update`: the GitHub releases of the vixeo_frontend CI (GitHubReleases, cached).
 * Any failure hides the version: a site page never breaks because of it.
 */
class LatestAndroidRelease
{
    /** @var array{version: string, notes: ?string, publishedAt: ?string, downloadUrl: ?string, sha256: ?string}|null|false */
    private array|null|false $release = false;

    public function __construct(private GitHubReleases $github) {}

    /**
     * The APK to offer: the latest release's file, or the one set in the .env.
     *
     * @return array{url: string, sha256: ?string}|null
     */
    public function apk(): ?array
    {
        $release = $this->get();
        if ($release !== null && $release['downloadUrl'] !== null) {
            return ['url' => $release['downloadUrl'], 'sha256' => $release['sha256']];
        }

        $url = config('vixeo.android.apk_url');

        return is_string($url) && $url !== ''
            ? ['url' => $url, 'sha256' => config('vixeo.android.apk_sha256') ?: null]
            : null;
    }

    /** @return array{version: string, notes: ?string, publishedAt: ?string, downloadUrl: ?string, sha256: ?string}|null */
    public function get(): ?array
    {
        if ($this->release !== false) {
            return $this->release;
        }

        try {
            $latest = $this->github->android()['latest'];
        } catch (Throwable $e) {
            // Cache (Redis) down, unexpected payload…: the page is shown without the version.
            Log::warning('Latest Android release unavailable for the site', ['error' => $e->getMessage()]);
            $latest = null;
        }

        return $this->release = $latest === null ? null : [
            'version' => $latest->version,
            'notes' => $latest->notes,
            'publishedAt' => $latest->publishedAt->toIso8601String(),
            // GitHubReleases only keeps https links.
            'downloadUrl' => $latest->downloadUrl,
            'sha256' => $latest->sha256,
        ];
    }
}
