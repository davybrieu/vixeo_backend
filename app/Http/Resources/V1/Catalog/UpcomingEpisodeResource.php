<?php

namespace App\Http\Resources\V1\Catalog;

use App\Services\Tmdb\TmdbFormat as F;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property array{show: array<string, mixed>, episode: array<string, mixed>, available_at: CarbonImmutable} $resource */
class UpcomingEpisodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $show = (array) $this->resource['show'];
        $episode = (array) $this->resource['episode'];
        /** @var CarbonImmutable $at */
        $at = $this->resource['available_at'];

        return [
            'show_id' => (int) $show['id'],
            'show_name' => (string) F::string($show, 'name'),
            /** TV show poster, w342. */
            'poster_url' => F::image(F::string($show, 'poster_path'), 'w342'),
            'season_number' => (int) ($episode['season_number'] ?? 0),
            'episode_number' => (int) ($episode['episode_number'] ?? 0),
            'name' => F::string($episode, 'name'),
            /** Date given by TMDb (the show's country). */
            'air_date' => (string) F::string($episode, 'air_date'),
            /** When the episode should be available, ISO 8601 in UTC: a guess, never early on purpose (docs/notifications.md). */
            'available_at' => (string) $at->toIso8601String(),
            /** Episode image, w300. */
            'still_url' => F::image(F::string($episode, 'still_path'), 'w300'),
        ];
    }
}
