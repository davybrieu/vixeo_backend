<?php

namespace App\Http\Resources\V1\Catalog;

use App\Enums\MediaType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Home row. "Voir tout": GET /catalog/{type}/lists/{list}, with the row's `type` and `list`.
 *
 * @property array<string, mixed> $resource TMDb data
 */
class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;

        return [
            'key' => (string) $r['key'],
            /** Display title, in French. */
            'title' => (string) $r['title'],
            'type' => self::type($r['type']),
            /** Name of the row for its "see all" page (`trending`, `popular`, `platform_netflix`…). */
            'list' => (string) $r['list'],
            'items' => MediaItemResource::collection((array) $r['items']),
        ];
    }

    private static function type(mixed $value): MediaType
    {
        return MediaType::from((string) $value);
    }
}
