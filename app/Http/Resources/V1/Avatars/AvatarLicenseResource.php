<?php

namespace App\Http\Resources\V1\Avatars;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A license (franchise) and its avatars.
 *
 * @property array<string, mixed> $resource catalog entry
 */
class AvatarLicenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = (array) $this->resource;

        return [
            'id' => (string) $r['id'],
            'name' => (string) $r['name'],
            'avatars' => AvatarResource::collection((array) ($r['avatars'] ?? [])),
        ];
    }
}
