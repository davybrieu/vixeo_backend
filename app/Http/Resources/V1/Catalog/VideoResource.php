<?php

namespace App\Http\Resources\V1\Catalog;

use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trailer (YouTube).
 *
 * @property array<string, mixed> $resource TMDb data
 */
class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $key = (string) F::string($r, 'key');

        return [
            'name' => F::string($r, 'name'),
            'site' => 'YouTube',
            /** YouTube video id. */
            'key' => $key,
            'url' => 'https://www.youtube.com/watch?v='.$key,
            /** ISO 639-1 language code ("fr", "en"). */
            'language' => F::string($r, 'iso_639_1'),
        ];
    }
}
