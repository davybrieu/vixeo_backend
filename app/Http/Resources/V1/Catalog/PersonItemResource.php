<?php

namespace App\Http\Resources\V1\Catalog;

use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Person summary (search results).
 *
 * @property array<string, mixed> $resource TMDb data
 */
class PersonItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $profile = F::string($r, 'profile_path');

        return [
            'id' => (int) $r['id'],
            'name' => (string) F::string($r, 'name'),
            /** "Acting", "Directing"… */
            'known_for_department' => F::string($r, 'known_for_department'),
            'profile_path' => $profile,
            'profile_url' => F::image($profile, 'w185'),
        ];
    }
}
