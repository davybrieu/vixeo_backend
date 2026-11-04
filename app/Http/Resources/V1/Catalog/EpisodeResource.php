<?php

namespace App\Http\Resources\V1\Catalog;

use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property array<string, mixed> $resource TMDb data */
class EpisodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $still = F::string($r, 'still_path');

        return [
            'id' => (int) $r['id'],
            'season_number' => (int) ($r['season_number'] ?? 0),
            'episode_number' => (int) ($r['episode_number'] ?? 0),
            'name' => (string) F::string($r, 'name'),
            'overview' => F::string($r, 'overview'),
            'air_date' => F::string($r, 'air_date'),
            /** Duration in minutes. */
            'runtime' => F::int($r, 'runtime'),
            'vote_average' => F::float($r, 'vote_average'),
            'still_path' => $still,
            /** Episode image, w300. */
            'still_url' => F::image($still, 'w300'),
        ];
    }
}
