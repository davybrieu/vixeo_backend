<?php

namespace App\Http\Resources\V1\Tmdb;

use App\Enums\RatedType;
use App\Models\TmdbRating;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A rating of the profile's TMDb account, with its comment ("Mes avis").
 *
 * @mixin TmdbRating
 */
class RatedItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $episode = $this->type === RatedType::Episode;

        return [
            'type' => $this->type,
            /** TMDb id of the movie or TV show (the page to open), also for an episode. */
            'id' => (int) $this->tmdb_id,
            /** Movie or TV show title. */
            'title' => (string) $this->title,
            /** Release, first air or episode air date, YYYY-MM-DD. */
            'date' => $this->date === null ? null : (string) $this->date,
            'year' => F::year($this->date === null ? null : (string) $this->date),
            'season_number' => $episode ? (int) $this->season_number : null,
            'episode_number' => $episode ? (int) $this->episode_number : null,
            'episode_name' => $this->episode_name === null ? null : (string) $this->episode_name,
            /** Poster of the movie or TV show, w342. */
            'poster_url' => F::image($this->poster_path === null ? null : (string) $this->poster_path, 'w342'),
            /** Backdrop, w1280. */
            'backdrop_url' => F::image($this->backdrop_path === null ? null : (string) $this->backdrop_path, 'w1280'),
            /** Episode image, w300. */
            'still_url' => F::image($this->still_path === null ? null : (string) $this->still_path, 'w300'),
            /** The profile's rating, 0.5 to 10. */
            'value' => (float) $this->value,
            /** The profile's comment, kept by Vixeo only (null without one). */
            'comment' => $this->comment === null ? null : (string) $this->comment,
            /** When it was rated (first seen by Vixeo for a rating given on themoviedb.org), ISO 8601. */
            'rated_at' => (string) $this->rated_at->toIso8601String(),
            /** TMDb average rating, out of 10. */
            'vote_average' => (float) $this->vote_average,
        ];
    }
}
