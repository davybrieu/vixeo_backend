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
 * An image a profile chose for itself: its photo (`ProfilePhotos`) or its cover (`ProfileCovers`).
 *
 * The API keeps the central part of the image with the expected shape, re-encoded (WebP, else
 * JPEG): nothing of the original file is kept, neither its metadata (location, camera) nor what
 * could hide in it. Stored on the private disk under a random name, which changes with each image
 * (cached for a year by the apps), and served by the API (public, like the name of the profile).
 */
abstract class ProfileImages
{
    public const DISK = 'local';

    /** Largest side accepted, in pixels (larger: the decoded image would weigh too much). */
    public const MAX_SIDE = 4096;

    /** Largest file accepted, in kilobytes. */
    public const MAX_KILOBYTES = 8192;

    /** A stored image's name. */
    public const NAME_PATTERN = '[A-Za-z0-9]{40}\.(webp|jpg)';

    /** Folder of the images on the disk. */
    abstract protected function directory(): string;

    /** Column of the profile that holds the image's name. */
    abstract protected function column(): string;

    /** Field of the upload, named in the errors. */
    abstract protected function field(): string;

    /** @return array{int, int} Width and height of the stored image, at most. */
    abstract protected function size(): array;

    /** [file] becomes the image of [profile]; the previous one is deleted. */
    public function store(Profile $profile, UploadedFile $file): void
    {
        [$width, $height] = $this->size();
        [$bytes, $extension] = $this->encode($this->crop($this->read((string) $file->getRealPath()), $width, $height));
        $name = Str::random(40).'.'.$extension;
        Storage::disk(self::DISK)->put($this->directory().'/'.$name, $bytes);

        $previous = $profile->getAttribute($this->column());
        $profile->forceFill([$this->column() => $name])->save();
        $this->forget([$previous]);
    }

    /** The image is removed and deleted. */
    public function remove(Profile $profile): void
    {
        $previous = $profile->getAttribute($this->column());
        if ($previous === null) {
            return;
        }
        $profile->forceFill([$this->column() => null])->save();
        $this->forget([$previous]);
    }

    /**
     * Deletes image files (a profile or an account deleted: the rows go by foreign key).
     *
     * @param  iterable<string|null>  $names
     */
    public function forget(iterable $names): void
    {
        $paths = [];
        foreach ($names as $name) {
            if (is_string($name) && preg_match('/^'.self::NAME_PATTERN.'$/', $name)) {
                $paths[] = $this->directory().'/'.$name;
            }
        }
        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }

    /** The image [name], cached for a year (its name changes with it). */
    public function response(string $name): Response
    {
        $path = $this->directory().'/'.$name;
        $disk = Storage::disk(self::DISK);
        abort_unless(preg_match('/^'.self::NAME_PATTERN.'$/', $name) && $disk->exists($path), 404);

        return response((string) $disk->get($path), 200, [
            'Content-Type' => str_ends_with($name, '.webp') ? 'image/webp' : 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** The image at [path], upright. */
    private function read(string $path): GdImage
    {
        $info = @getimagesize($path);
        $image = $info === false ? false : @imagecreatefromstring((string) file_get_contents($path));
        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([$this->field() => __('This image cannot be read.')]);
        }

        return $info[2] === IMAGETYPE_JPEG ? $this->upright($image, $path) : $image;
    }

    /** The central part of [image] with the shape [width]:[height], [width] × [height] pixels at most. */
    private function crop(GdImage $image, int $width, int $height): GdImage
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        // The largest part with the expected shape.
        if ($sourceWidth * $height > $sourceHeight * $width) {
            $cropHeight = $sourceHeight;
            $cropWidth = max(1, intdiv($sourceHeight * $width, $height));
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = max(1, intdiv($sourceWidth * $height, $width));
        }
        // Never enlarged.
        $scale = min(1, $width / $cropWidth);
        $outWidth = max(1, (int) round($cropWidth * $scale));
        $outHeight = max(1, (int) round($cropHeight * $scale));

        $out = imagecreatetruecolor($outWidth, $outHeight);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled(
            $out, $image, 0, 0, intdiv($sourceWidth - $cropWidth, 2), intdiv($sourceHeight - $cropHeight, 2),
            $outWidth, $outHeight, $cropWidth, $cropHeight,
        );

        return $out;
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
