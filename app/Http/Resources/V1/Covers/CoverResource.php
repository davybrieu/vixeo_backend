<?php

namespace App\Http\Resources\V1\Covers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A cover, drawn by the apps: a pattern and its colors.
 *
 * @property array<string, mixed> $resource catalog entry
 */
class CoverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;

        return [
            /** Value to store in the profile's `cover`. */
            'id' => (string) $r['id'],
            'name' => (string) $r['name'],
            /**
             * How it is drawn: `linear` (a gradient along `angle`), `glow` (a background and soft
             * lights), `stripes`, `dots`, `waves`, `grid` (a retro sun over a grid), `film` (a film
             * strip), `spotlight` (beams of light), `curtain` (a theater curtain), `stars` (a starry
             * night). An app that doesn't know a pattern draws a `linear` gradient of the colors.
             */
            'pattern' => (string) ($r['pattern'] ?? 'linear'),
            /**
             * Colors (#RRGGBB), in the order the pattern reads them (the background first).
             *
             * @var list<string>
             */
            'colors' => $this->colors(),
            /** Direction of a `linear` gradient, in degrees: 0 = left to right, 90 = top to bottom. */
            'angle' => (int) ($r['angle'] ?? 135),
        ];
    }

    /** @return list<string> */
    public function colors(): array
    {
        $colors = ((array) $this->resource)['colors'] ?? [];

        return is_array($colors) ? array_values(array_map(strval(...), $colors)) : [];
    }
}
