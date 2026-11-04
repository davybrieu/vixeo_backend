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
        ];
    }
}
