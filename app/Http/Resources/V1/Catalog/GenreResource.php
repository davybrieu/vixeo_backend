<?php

namespace App\Http\Resources\V1\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property array<string, mixed> $resource TMDb data */
class GenreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;

        return [
            'id' => (int) $r['id'],
            'name' => (string) ($r['name'] ?? ''),
        ];
    }
}
