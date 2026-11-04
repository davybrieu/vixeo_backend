<?php

namespace App\Http\Resources\V1\Avatars;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An avatar: pixel art (`pixels` + `palette`) or an image (`image_url`).
 *
 * @property array<string, mixed> $resource catalog entry
 */
class AvatarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;

        return [
            /** Value to store in the profile's `avatar`. */
            'id' => (string) $r['id'],
            'name' => (string) $r['name'],
            /** Tile background color (#RRGGBB). */
            'background' => (string) ($r['background'] ?? '#1F2722'),
            /** Square image, when the avatar is a picture. */
            'image_url' => isset($r['image_url']) ? (string) $r['image_url'] : null,
            /**
             * Pixel-art rows, top to bottom; each character is a palette key, "." is transparent.
             *
             * @var list<string>
             */
            'pixels' => $this->rows(),
            /**
             * Palette: character → color (#RRGGBB).
             *
             * @var array<string, string>
             */
            'palette' => $this->palette(),
        ];
    }

    /** @return list<string> */
    public function rows(): array
    {
        $rows = ((array) $this->resource)['pixels'] ?? [];

        return is_array($rows) ? array_values(array_map(strval(...), $rows)) : [];
    }

    /** @return array<string, string> */
    public function palette(): array
    {
        $palette = ((array) $this->resource)['palette'] ?? [];

        return is_array($palette) ? array_map(strval(...), $palette) : [];
    }
}
