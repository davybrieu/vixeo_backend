<?php

namespace App\Http\Resources\V1\Catalog;

use App\Enums\MediaType;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everyone of a movie / TV show page, for « Créateur et distribution » → « Voir tout ».
 *
 * @property array<string, mixed> $resource TMDb movie (with `credits`) or TV show (with `aggregate_credits`)
 */
class CreditsResource extends JsonResource
{
    public function __construct(mixed $resource, private MediaType $type)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $movie = $this->type === MediaType::Movie;

        return [
            /** Directors (movie) or creators (TV show). */
            'crew' => CrewResource::collection($movie ? F::directors($r) : F::array($r, 'created_by')),
            /** The whole cast in billing order (over all seasons for a TV show). */
            'cast' => CastResource::collection(F::array(F::array($r, $movie ? 'credits' : 'aggregate_credits'), 'cast')),
        ];
    }
}
