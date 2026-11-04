<?php

namespace App\Http\Resources\V1\Catalog;

use App\Enums\MediaType;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Movie page.
 *
 * @property array<string, mixed> $resource TMDb data
 */
class MovieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $date = F::string($r, 'release_date');
        $certification = F::certification($r);
        $trailer = F::trailer($r);
        $credits = F::array($r, 'credits');
        $directors = array_values(array_filter(
            F::array($credits, 'crew'),
            fn ($member) => is_array($member) && ($member['job'] ?? null) === 'Director',
        ));
        $poster = F::string($r, 'poster_path');
        $backdrop = F::string($r, 'backdrop_path');

        return [
            'id' => (int) $r['id'],
            'type' => MediaType::Movie,
            'title' => (string) F::string($r, 'title'),
            'original_title' => F::string($r, 'original_title'),
            'original_language' => F::string($r, 'original_language'),
            'tagline' => F::string($r, 'tagline'),
            'overview' => F::string($r, 'overview'),
            'release_date' => $date,
            'year' => F::year($date),
            /** Duration in minutes. */
            'runtime' => F::int($r, 'runtime'),
            /** "Released", "Post Production"… */
            'status' => F::string($r, 'status'),
            'genres' => GenreResource::collection(F::array($r, 'genres')),
            'vote_average' => F::float($r, 'vote_average'),
            'vote_count' => (int) ($r['vote_count'] ?? 0),
            /** Raw certification, French if available, else American ("12", "PG-13"). */
            'certification' => $certification,
            /** Minimum age derived from the certification (0 = all audiences). Null if unknown. */
            'age_rating' => F::ageRating($certification),
            'imdb_id' => F::string($r, 'imdb_id'),
            'poster_path' => $poster,
            'poster_url' => F::image($poster, 'w500'),
            'backdrop_path' => $backdrop,
            'backdrop_url' => F::image($backdrop, 'w1280'),
            'trailer' => $trailer !== null ? new VideoResource($trailer) : null,
            'directors' => CrewResource::collection($directors),
            /** Main cast (20 max), in billing order. */
            'cast' => CastResource::collection(array_slice(F::array($credits, 'cast'), 0, 20)),
            /** "Vous pourriez aussi aimer". */
            'recommended' => MediaItemResource::collection(F::array($r, 'recommended')),
        ];
    }
}
