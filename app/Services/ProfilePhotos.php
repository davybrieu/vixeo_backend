<?php

namespace App\Services;

use App\Models\Profile;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A profile's own photo, shown instead of its avatar (developer's request, 23/11/2026; docs/social.md).
 *
 * The app frames the photo itself and sends a square; the API keeps its central square anyway,
 * 512 pixels, re-encoded (WebP, else JPEG): nothing of the original file is kept, neither its
 * metadata (location, camera) nor what could hide in it. Stored on the private disk under a
 * random name, which changes with each photo (cached for a year by the apps), and served by the
 * API (`GET /profile-photos/{name}`, public like the name of the profile).
 */
class ProfilePhotos
{
    public const DISK = 'local';

    public const DIRECTORY = 'profile-photos';

    /** Side of the stored photo, in pixels. */
    public const SIZE = 512;

    /** Sides accepted, in pixels (larger: the decoded image would weigh too much). */
    public const MIN_SIDE = 128;

    public const MAX_SIDE = 4096;

    /** Largest file accepted, in kilobytes. */
    public const MAX_KILOBYTES = 8192;

    /** A stored photo's name (`GET /profile-photos/{name}`). */
    public const NAME_PATTERN = '[A-Za-z0-9]{40}\.(webp|jpg)';

    /** Public address of a photo, null without one. */
    public static function url(?string $name): ?string
    {
        return $name === null || $name === '' ? null : route('v1.profile-photos.show', ['name' => $name]);
    }

    /** [file] becomes the photo of [profile]; the previous one is deleted. */
    public function store(Profile $profile, UploadedFile $file): void
    {
        [$bytes, $extension] = $this->encode($this->square((string) $file->getRealPath()));
        $name = Str::random(40).'.'.$extension;
        Storage::disk(self::DISK)->put(self::DIRECTORY.'/'.$name, $bytes);

        $previous = $profile->photo_path;
        $profile->forceFill(['photo_path' => $name])->save();
        $this->forget([$previous]);
    }

    /** Back to the avatar: the photo is deleted. */
    public function remove(Profile $profile): void
    {
        $previous = $profile->photo_path;
        if ($previous === null) {
            return;
        }
        $profile->forceFill(['photo_path' => null])->save();
        $this->forget([$previous]);
    }

    /**
     * Deletes photo files (a profile or an account deleted: the rows go by foreign key).
     *
     * @param  iterable<string|null>  $names
     */
    public function forget(iterable $names): void
    {
        $paths = [];
        foreach ($names as $name) {
            if (is_string($name) && preg_match('/^'.self::NAME_PATTERN.'$/', $name)) {
                $paths[] = self::DIRECTORY.'/'.$name;
            }
        }
        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }

    /** The photo [name], cached for a year (its name changes with it). */
    public function response(string $name): Response
    {
        $path = self::DIRECTORY.'/'.$name;
        $disk = Storage::disk(self::DISK);
        abort_unless(preg_match('/^'.self::NAME_PATTERN.'$/', $name) && $disk->exists($path), 404);

        return response((string) $disk->get($path), 200, [
            'Content-Type' => str_ends_with($name, '.webp') ? 'image/webp' : 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** The central square of the image at [path], upright, [SIZE] pixels at most. */
    private function square(string $path): GdImage
    {
        $info = @getimagesize($path);
        $image = $info === false ? false : @imagecreatefromstring((string) file_get_contents($path));
        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages(['photo' => __('This image cannot be read.')]);
        }
        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->upright($image, $path);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $size = min(self::SIZE, $side);
        $square = imagecreatetruecolor($size, $size);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagefill($square, 0, 0, imagecolorallocatealpha($square, 0, 0, 0, 127));
        imagecopyresampled($square, $image, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $size, $size, $side, $side);

        return $square;
    }

    /** A photo taken sideways (EXIF orientation of a JPEG) turned upright. */
    private function upright(GdImage $image, string $path): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        // Angles counterclockwise (GD), then a mirror for the mirrored orientations.
        [$angle, $mirror] = match ($orientation) {
            2 => [0, true],
            3 => [180, false],
            4 => [180, true],
            5 => [270, true],
            6 => [270, false],
            7 => [90, true],
            8 => [90, false],
            default => [0, false],
        };
        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated instanceof GdImage) {
                $image = $rotated;
            }
        }
        if ($mirror) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    /** @return array{string, string} The bytes and the extension. */
    private function encode(GdImage $image): array
    {
        ob_start();
        $webp = function_exists('imagewebp') && imagewebp($image, null, 86);
        if (! $webp) {
            ob_clean();
            imagejpeg($image, null, 88);
        }

        return [(string) ob_get_clean(), $webp ? 'webp' : 'jpg'];
    }
}
