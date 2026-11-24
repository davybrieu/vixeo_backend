<?php

namespace App\Http\Resources\V1\Catalog;

use App\Enums\MediaType;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everyone of a movie / TV show page, for « Créateur et distribution » → « Voir tout », page by
 * page (developer's request, 24/11/2026: 20 at a time).
 *
 * @property array<string, mixed> $resource TMDb movie (with `credits`) or TV show (with `aggregate_credits`)
 */
class CreditsResource extends JsonResource
{
    /** Cast members a page. */
    public const PAGE = 20;

    public function __construct(mixed $resource, private MediaType $type, private int $page = 1)
    {
        parent::__construct($resource);
    }

    /**
     * The whole cast in billing order (over all seasons for a TV show).
     *
     * @param  array<string, mixed>  $title
     * @return list<array<string, mixed>>
     */
    public static function cast(array $title, MediaType $type): array
    {
        return array_values(F::array(F::array($title, $type === MediaType::Movie ? 'credits' : 'aggregate_credits'), 'cast'));
    }

    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;
        $movie = $this->type === MediaType::Movie;

        return [
            /** Directors (movie) or creators (TV show): on the first page only. */
            'crew' => CrewResource::collection($this->page > 1 ? [] : ($movie ? F::directors($r) : F::array($r, 'created_by'))),
            /** 20 cast members of the page, in billing order. */
            'cast' => CastResource::collection(array_slice(self::cast($r, $this->type), ($this->page - 1) * self::PAGE, self::PAGE)),
        ];
    }
}
