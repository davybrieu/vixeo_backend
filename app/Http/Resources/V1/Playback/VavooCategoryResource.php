<?php

namespace App\Http\Resources\V1\Playback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Vavoo channel category (Sport, Cinéma, Info…).
 *
 * @property array{id: string, name: string} $resource
 */
class VavooCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource['id'],
            'name' => (string) $this->resource['name'],
        ];
    }
}
