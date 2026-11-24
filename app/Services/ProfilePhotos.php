<?php

namespace App\Services;

/**
 * A profile's own photo, shown instead of its avatar (developer's request, 23/11/2026; docs/social.md).
 *
 * The app frames the photo itself and sends a square; the API keeps its central square anyway,
 * 512 pixels, re-encoded without the metadata of the file (`ProfileImages`), served by
 * `GET /profile-photos/{name}`.
 */
class ProfilePhotos extends ProfileImages
{
    public const DIRECTORY = 'profile-photos';

    /** Side of the stored photo, in pixels. */
    public const SIZE = 512;

    /** Smallest side accepted, in pixels. */
    public const MIN_SIDE = 128;

    /** Public address of a photo, null without one. */
    public static function url(?string $name): ?string
    {
        return $name === null || $name === '' ? null : route('v1.profile-photos.show', ['name' => $name]);
    }

    protected function directory(): string
    {
        return self::DIRECTORY;
    }

    protected function column(): string
    {
        return 'photo_path';
    }

    protected function field(): string
    {
        return 'photo';
    }

    protected function size(): array
    {
        return [self::SIZE, self::SIZE];
    }
}
