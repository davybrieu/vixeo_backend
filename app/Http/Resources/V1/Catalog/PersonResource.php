<?php

namespace App\Http\Resources\V1\Catalog;

use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Actor / director page with filmography.
 *
 * @property array<string, mixed> $resource TMDb data
 */
class PersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $profile = F::string($r, 'profile_path');

        return [
            'id' => (int) $r['id'],
            'name' => (string) F::string($r, 'name'),
            'biography' => F::string($r, 'biography'),
            'birthday' => F::string($r, 'birthday'),
            'deathday' => F::string($r, 'deathday'),
            'place_of_birth' => F::string($r, 'place_of_birth'),
            'known_for_department' => F::string($r, 'known_for_department'),
            'profile_path' => $profile,
            'profile_url' => F::image($profile, 'h632'),
            /** First 20 movies (the rest: `GET /catalog/person/{id}/credits?type=movie&page=`). */
            'movies' => MediaItemResource::collection(F::array($r, 'movies')),
            /** First 20 TV shows (the rest: `GET /catalog/person/{id}/credits?type=tv&page=`). */
            'shows' => MediaItemResource::collection(F::array($r, 'shows')),
        ];
    }
}
