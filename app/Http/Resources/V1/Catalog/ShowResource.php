<?php

namespace App\Http\Resources\V1\Catalog;

use App\Enums\MediaType;
use App\Services\TitleArtworks;
use App\Services\Tmdb\TitleCrew;
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
        $logo = F::logo($r);
        $textless = F::textlessBackdrop($r);
        // The images the active profile chose for this title (TitleArtworks): its backdrop is the
        // one under the logo too.
        $chosen = TitleArtworks::chosen($request, MediaType::Tv, (int) $r['id']);
        if ($chosen !== null) {
            $poster = $chosen['poster'] ?? $poster;
            $backdrop = $chosen['backdrop'] ?? $backdrop;
            $textless = $chosen['backdrop'] ?? $textless;
            $logo = $chosen['logo'] !== null ? ['path' => $chosen['logo'], 'aspect_ratio' => $chosen['logo_aspect_ratio']] : $logo;
        }
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
            /** IMDb's and TMDb's ratings with their votes (docs/notes.md): the apps show IMDb's, else TMDb's. */
            'ratings' => new RatingsResource($r),
            /** Raw certification, French if available, else American ("12", "TV-MA"). */
            'certification' => $certification,
            /** Minimum age derived from the certification (0 = all audiences). Null if unknown. */
            'age_rating' => F::ageRating($certification),
            'imdb_id' => F::string($r, 'imdb_id') ?? F::string($externalIds, 'imdb_id'),
            'tvdb_id' => F::int($externalIds, 'tvdb_id'),
            'poster_path' => $poster,
            'poster_url' => F::image($poster, 'w500'),
            'backdrop_path' => $backdrop,
            'backdrop_url' => F::image($backdrop, 'w1280'),
            /** Title logo (transparent PNG, w500), French if available. Null: write the title. */
            'logo_path' => $logo['path'] ?? null,
            'logo_url' => F::image($logo['path'] ?? null, 'w500'),
            /** Logo width / height, to size it before it loads. */
            'logo_aspect_ratio' => isset($logo['aspect_ratio']) ? (float) $logo['aspect_ratio'] : null,
            /** Backdrop without text (to show under the logo), w1280. Null: use `backdrop_url`. */
            'textless_backdrop_path' => $textless,
            'textless_backdrop_url' => F::image($textless, 'w1280'),
            'trailer' => $trailer !== null ? new VideoResource($trailer) : null,
            /** « Bandes-annonces »: every trailer and teaser on YouTube (20 at most), the best first. */
            'trailers' => VideoResource::collection(array_slice(F::trailers($r), 0, 20)),
            'creators' => CrewResource::collection(F::array($r, 'created_by')),
            /** « Équipe technique »: the creators, then everyone but the actors (20 max), their jobs in French. */
            'crew' => CrewMemberResource::collection(array_slice(TitleCrew::of($r, MediaType::Tv), 0, 20)),
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
