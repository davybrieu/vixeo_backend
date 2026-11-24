<?php

namespace App\Http\Resources\V1\Covers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A style of covers (gradients, lights, patterns, cinema) and its covers.
 *
 * @property array<string, mixed> $resource catalog entry
 */
class CoverStyleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;

        return [
            'id' => (string) $r['id'],
            'name' => (string) $r['name'],
            'covers' => CoverResource::collection((array) ($r['covers'] ?? [])),
        ];
    }
}
