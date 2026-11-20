<?php

namespace App\Http\Resources\V1\Catalog;

use App\Enums\MediaType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ratings of a title on other sites, from MDBList (docs/notes.md). Each value is on its site's
 * scale; null when the site has no rating for the title.
 *
 * @property array{type: MediaType, id: int, ratings: array<string, int|float|null>} $resource
 */
class TitleRatingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource['ratings'];
        $float = fn (string $source): ?float => isset($r[$source]) ? (float) $r[$source] : null;
        $int = fn (string $source): ?int => isset($r[$source]) ? (int) $r[$source] : null;

        return [
            /** @var MediaType */
            'type' => $this->resource['type'],
            /** TMDb id. */
            'id' => (int) $this->resource['id'],
            /** IMDb, out of 10. */
            'imdb' => $float('imdb'),
            /** TMDb, out of 10. */
            'tmdb' => $float('tmdb'),
            /** Trakt, out of 100. */
            'trakt' => $int('trakt'),
            /** Letterboxd, out of 5 (movies). */
            'letterboxd' => $float('letterboxd'),
            /** Rotten Tomatoes critics (Tomatometer), percentage. */
            'tomatoes' => $int('tomatoes'),
            /** Rotten Tomatoes audience (Popcornmeter), percentage. */
            'audience' => $int('audience'),
            /** Metacritic, out of 100. */
            'metacritic' => $int('metacritic'),
            /** MyAnimeList, out of 10 (anime). */
            'mal' => $float('mal'),
        ];
    }
}
