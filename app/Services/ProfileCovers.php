<?php

namespace App\Services;

/**
 * A profile's own cover, shown instead of the cover of the catalog (`CoverCatalog`) at the top of
 * its page (developer's request, 24/11/2026; docs/social.md).
 *
 * The app frames the image itself and sends a 3:1 banner; the API keeps its central 3:1 part
 * anyway, 1,500 × 500 pixels at most, re-encoded without the metadata of the file
 * (`ProfileImages`), served by `GET /profile-covers/{name}`.
 */
class ProfileCovers extends ProfileImages
{
    public const DIRECTORY = 'profile-covers';

    /** Size of the stored cover, in pixels (3:1). */
    public const WIDTH = 1500;

    public const HEIGHT = 500;

    /** Smallest size accepted, in pixels. */
    public const MIN_WIDTH = 600;

    public const MIN_HEIGHT = 200;

    /** Public address of a cover, null without one. */
    public static function url(?string $name): ?string
    {
        return $name === null || $name === '' ? null : route('v1.profile-covers.show', ['name' => $name]);
    }

    protected function directory(): string
    {
        return self::DIRECTORY;
    }

    protected function column(): string
    {
        return 'cover_path';
    }

    protected function field(): string
    {
        return 'cover';
    }

    protected function size(): array
    {
        return [self::WIDTH, self::HEIGHT];
    }
}
