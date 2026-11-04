<?php

namespace App\Http\Resources\V1\Catalog;

use App\Enums\MediaType;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TV show page.
 *
 * @property array<string, mixed> $resource TMDb data
 */
class ShowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $date = F::string($r, 'first_air_date');
        $certification = F::certification($r);
        $trailer = F::trailer($r);
        $nextEpisode = F::array($r, 'next_episode_to_air');
        $poster = F::string($r, 'poster_path');
        $backdrop = F::string($r, 'backdrop_path');
        $externalIds = F::array($r, 'external_ids');

        return [
            'id' => (int) $r['id'],
            'type' => MediaType::Tv,
            'title' => (string) F::string($r, 'name'),
            'original_title' => F::string($r, 'original_name'),
            'original_language' => F::string($r, 'original_language'),
            'tagline' => F::string($r, 'tagline'),
            'overview' => F::string($r, 'overview'),
            'first_air_date' => $date,
            'last_air_date' => F::string($r, 'last_air_date'),
            'year' => F::year($date),
            /** "Returning Series", "Ended", "Canceled"… */
            'status' => F::string($r, 'status'),
            'in_production' => (bool) ($r['in_production'] ?? false),
            'number_of_seasons' => (int) ($r['number_of_seasons'] ?? 0),
            'number_of_episodes' => (int) ($r['number_of_episodes'] ?? 0),
            /** Typical episode duration in minutes. */
            'episode_runtime' => F::int(F::array($r, 'episode_run_time'), '0'),
            'genres' => GenreResource::collection(F::array($r, 'genres')),
            'vote_average' => F::float($r, 'vote_average'),
            'vote_count' => (int) ($r['vote_count'] ?? 0),
            /** Raw certification, French if available, else American ("12", "TV-MA"). */
            'certification' => $certification,
            /** Minimum age derived from the certification (0 = all audiences). Null if unknown. */
            'age_rating' => F::ageRating($certification),
            'imdb_id' => F::string($externalIds, 'imdb_id'),
            'tvdb_id' => F::int($externalIds, 'tvdb_id'),
            'poster_path' => $poster,
            'poster_url' => F::image($poster, 'w500'),
            'backdrop_path' => $backdrop,
            'backdrop_url' => F::image($backdrop, 'w1280'),
            'trailer' => $trailer !== null ? new VideoResource($trailer) : null,
            'creators' => CrewResource::collection(F::array($r, 'created_by')),
            /** Main cast over all seasons (20 max). */
            'cast' => CastResource::collection(array_slice(F::array(F::array($r, 'aggregate_credits'), 'cast'), 0, 20)),
            /** Episodes: GET /catalog/tv/{id}/season/{season_number}. */
            'seasons' => SeasonSummaryResource::collection(F::array($r, 'seasons')),
            'next_episode' => $nextEpisode !== [] ? new EpisodeResource($nextEpisode) : null,
            /** "Vous pourriez aussi aimer". */
            'recommended' => MediaItemResource::collection(F::array($r, 'recommended')),
        ];
    }
}
