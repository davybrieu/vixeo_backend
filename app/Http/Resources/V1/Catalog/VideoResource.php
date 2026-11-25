<?php

namespace App\Http\Resources\V1\Catalog;

use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trailer or teaser (YouTube).
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
            'url' => 'https://www.youtube.com/watch?v='.rawurlencode($key),
            /** ISO 639-1 language code ("fr", "en"). */
            'language' => F::string($r, 'iso_639_1'),
            /** `Trailer` (bande-annonce) or `Teaser`. */
            'type' => ($r['type'] ?? null) === 'Teaser' ? 'Teaser' : 'Trailer',
            /** YouTube's still of the video, 480 × 360 (a 16:9 picture between black bands). */
            'thumbnail_url' => 'https://i.ytimg.com/vi/'.rawurlencode($key).'/hqdefault.jpg',
            /** When YouTube published it (ISO 8601), if TMDb knows. */
            'published_at' => F::string($r, 'published_at'),
        ];
    }
}
