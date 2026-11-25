<?php

namespace App\Http\Resources\V1\Catalog;

use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Someone of « Équipe technique » (App\Services\Tmdb\TitleCrew).
 *
 * @property array{id: int, name: string, profile_path: string|null, jobs: list<string>} $resource
 */
class CrewMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $profile = isset($r['profile_path']) ? (string) $r['profile_path'] : null;
        /** @var list<string> $jobs */
        $jobs = array_values(array_map(strval(...), (array) ($r['jobs'] ?? [])));

        return [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'profile_path' => $profile,
            'profile_url' => F::image($profile, 'w185'),
            /** Its jobs on this title, in French, the most important first: « Réalisation, Scénario ». */
            'job' => implode(', ', $jobs),
            /**
             * The same, one by one.
             *
             * @var list<string>
             */
            'jobs' => $jobs,
        ];
    }
}
