<?php

namespace App\Http\Resources\V1\Catalog;

use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property array<string, mixed> $resource TMDb data */
class SeasonSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $poster = F::string($r, 'poster_path');

        return [
            'id' => (int) $r['id'],
            /** 0 = specials. */
            'season_number' => (int) ($r['season_number'] ?? 0),
            'name' => (string) F::string($r, 'name'),
            'overview' => F::string($r, 'overview'),
            'air_date' => F::string($r, 'air_date'),
            'episode_count' => (int) ($r['episode_count'] ?? 0),
            'poster_path' => $poster,
            'poster_url' => F::image($poster, 'w342'),
        ];
    }
}
