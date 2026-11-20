<?php

namespace App\Http\Resources\V1\Tmdb;

use App\Models\TmdbRating;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A review of the profile: rating and comment ("Mes avis").
 *
 * @mixin TmdbRating
 */
class RatedItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            /** TMDb id of the movie or TV show (the page to open). */
            'id' => (int) $this->tmdb_id,
            /** Movie or TV show title. */
            'title' => (string) $this->title,
            /** Release or first air date, YYYY-MM-DD. */
            'date' => $this->date === null ? null : (string) $this->date,
            'year' => F::year($this->date === null ? null : (string) $this->date),
            /** Poster of the movie or TV show, w342. */
            'poster_url' => F::image($this->poster_path === null ? null : (string) $this->poster_path, 'w342'),
            /** Backdrop, w1280. */
            'backdrop_url' => F::image($this->backdrop_path === null ? null : (string) $this->backdrop_path, 'w1280'),
            /** The profile's rating, 0.5 to 10. */
            'value' => (float) $this->value,
            /** The profile's comment (null without one). */
            'comment' => $this->comment === null ? null : (string) $this->comment,
            /** When it was rated, ISO 8601. */
            'rated_at' => (string) $this->rated_at->toIso8601String(),
            /** TMDb average rating, out of 10. */
            'vote_average' => (float) $this->vote_average,
        ];
    }
}
