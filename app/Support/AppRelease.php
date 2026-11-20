<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A published version of the Android or Windows app: a GitHub release of the CI
 * (App\Services\GitHubReleases).
 */
final readonly class AppRelease
{
    public function __construct(
        public string $version,
        public int $build,
        /** Marked « [obligatoire] » in its GitHub title: older installs must update. */
        public bool $required,
        public ?string $notes,
        /** The APK or the Windows installer (https): the app downloads and runs it. */
        public ?string $downloadUrl,
        /** SHA-256 of that file, checked by the app before installing. */
        public ?string $sha256,
        public Carbon $publishedAt,
    ) {}
}
