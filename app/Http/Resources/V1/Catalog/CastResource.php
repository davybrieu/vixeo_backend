<?php

namespace App\Http\Resources\V1\Catalog;

use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property array<string, mixed> $resource TMDb data */
class CastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $profile = F::string($r, 'profile_path');
        // TV shows (aggregate credits) list roles instead of a single character.
        $character = F::string($r, 'character') ?? F::string(F::array(F::array($r, 'roles'), 0), 'character');

        return [
            /** Person id: GET /catalog/person/{id} for the filmography. */
            'id' => (int) $r['id'],
            'name' => (string) F::string($r, 'name'),
            'character' => $character,
            'profile_path' => $profile,
            'profile_url' => F::image($profile, 'w185'),
        ];
    }
}
