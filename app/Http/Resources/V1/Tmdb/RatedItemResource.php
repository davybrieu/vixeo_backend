<?php

namespace App\Http\Resources\V1\Tmdb;

use App\Enums\RatedType;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A rating of the profile's TMDb account ("Mes notes").
 *
 * @property array{type: RatedType, item: array<string, mixed>, show: array<string, mixed>|null} $resource
 */
class RatedItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->type();
        $item = $this->resource['item'];
        // Episodes: the TV show gives the title and the poster.
        $title = $type === RatedType::Episode ? ($this->resource['show'] ?? []) : $item;
        $poster = F::string($title, 'poster_path');
        $backdrop = F::string($title, 'backdrop_path');
        $still = $type === RatedType::Episode ? F::string($item, 'still_path') : null;
        $date = F::string($item, match ($type) {
            RatedType::Movie => 'release_date',
            RatedType::Tv => 'first_air_date',
            RatedType::Episode => 'air_date',
        });

        return [
            'type' => $type,
            /** TMDb id of the movie or TV show (the page to open), also for an episode. */
            'id' => (int) ($type === RatedType::Episode ? ($item['show_id'] ?? 0) : ($item['id'] ?? 0)),
            /** Movie or TV show title. */
            'title' => (string) F::string($title, $type === RatedType::Movie ? 'title' : 'name'),
            /** Release, first air or episode air date, YYYY-MM-DD. */
            'date' => $date,
            'year' => F::year($date),
            'season_number' => $type === RatedType::Episode ? (int) ($item['season_number'] ?? 0) : null,
            'episode_number' => $type === RatedType::Episode ? (int) ($item['episode_number'] ?? 0) : null,
            'episode_name' => $type === RatedType::Episode ? F::string($item, 'name') : null,
            /** Poster of the movie or TV show, w342. */
            'poster_url' => F::image($poster, 'w342'),
            /** Backdrop, w1280. */
            'backdrop_url' => F::image($backdrop, 'w1280'),
            /** Episode image, w300. */
            'still_url' => F::image($still, 'w300'),
            /** The profile's rating, 0.5 to 10. */
            'value' => F::float($item, 'rating'),
            /** TMDb average rating, out of 10. */
            'vote_average' => F::float($item, 'vote_average'),
        ];
    }

    /** Movie, TV show or episode. */
    private function type(): RatedType
    {
        return $this->resource['type'];
    }
}
