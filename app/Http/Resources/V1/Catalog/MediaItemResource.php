<?php

namespace App\Http\Resources\V1\Catalog;

use App\Enums\MediaType;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Movie or TV show summary (home rows, lists, search, recommendations, filmography).
 *
 * @property array<string, mixed> $resource TMDb data
 */
class MediaItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $type = F::mediaType($r['media_type'] ?? null);
        $movie = $type === MediaType::Movie;
        $date = F::string($r, $movie ? 'release_date' : 'first_air_date');
        $poster = F::string($r, 'poster_path');
        $backdrop = F::string($r, 'backdrop_path');
        $logo = is_array($r['logo'] ?? null) ? $r['logo'] : null;

        return [
            /** TMDb id. */
            'id' => (int) $r['id'],
            'type' => $type,
            'title' => (string) F::string($r, $movie ? 'title' : 'name'),
            'original_title' => F::string($r, $movie ? 'original_title' : 'original_name'),
            'overview' => F::string($r, 'overview'),
            /** Release date (movie) or first air date (TV show), YYYY-MM-DD. */
            'date' => $date,
            'year' => F::year($date),
            /** Average rating out of 10. */
            'vote_average' => F::float($r, 'vote_average'),
            /** @var list<int> */
            'genre_ids' => F::ints($r, 'genre_ids'),
            'poster_path' => $poster,
            /** Poster, w342. Build other sizes with `poster_path` (see /catalog/config). */
            'poster_url' => F::image($poster, 'w342'),
            'backdrop_path' => $backdrop,
            /** Backdrop, w1280. */
            'backdrop_url' => F::image($backdrop, 'w1280'),
            /** Title logo (transparent PNG, w500). Home rows only: null elsewhere (the title page has it) or when TMDb has none. */
            'logo_url' => F::image(isset($logo['path']) ? (string) $logo['path'] : null, 'w500'),
            /** Logo width / height. */
            'logo_aspect_ratio' => isset($logo['aspect_ratio']) ? (float) $logo['aspect_ratio'] : null,
        ];
    }
}
