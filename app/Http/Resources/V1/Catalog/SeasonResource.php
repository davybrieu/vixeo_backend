<?php

namespace App\Http\Resources\V1\Catalog;

use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property array<string, mixed> $resource TMDb data */
class SeasonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $poster = F::string($r, 'poster_path');

        return [
            'id' => (int) $r['id'],
            'show_id' => (int) $r['show_id'],
            'season_number' => (int) ($r['season_number'] ?? 0),
            'name' => (string) F::string($r, 'name'),
            'overview' => F::string($r, 'overview'),
            'air_date' => F::string($r, 'air_date'),
            'poster_path' => $poster,
            'poster_url' => F::image($poster, 'w342'),
            'episodes' => EpisodeResource::collection(F::array($r, 'episodes')),
        ];
    }
}
