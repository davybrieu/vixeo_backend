<?php

namespace App\Http\Resources\V1\Catalog;

use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ratings of a movie, a TV show or an episode (docs/notes.md): IMDb's (imported from IMDb's
 * datasets, linked by the IMDb id) and TMDb's, each with its number of votes. The apps show
 * IMDb's, else TMDb's.
 *
 * @property array<string, mixed> $resource TMDb data with `imdb_rating` (ImdbRatings)
 */
class RatingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $imdb = is_array($r['imdb_rating'] ?? null) ? $r['imdb_rating'] : null;

        return [
            /** IMDb's rating out of 10 and its votes; null when IMDb has none (or the title isn't linked). */
            'imdb' => $imdb === null ? null : [
                /** IMDb id (tt…). */
                'id' => (string) $imdb['id'],
                'rating' => (float) $imdb['rating'],
                'votes' => (int) $imdb['votes'],
            ],
            /** TMDb's rating out of 10 and its votes (null rating: no vote yet). */
            'tmdb' => [
                'rating' => ((int) ($r['vote_count'] ?? 0)) > 0 ? F::float($r, 'vote_average') : null,
                'votes' => (int) ($r['vote_count'] ?? 0),
            ],
        ];
    }
}
