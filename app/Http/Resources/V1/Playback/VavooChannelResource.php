<?php

namespace App\Http\Resources\V1\Playback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A live channel offered by Vavoo. The channel URL and the signature stay on the server: ask for
 * `GET /tv/vavoo/channels/{id}/stream` to play it.
 *
 * @property array{id: string, name: string, category_id: string, logo: string|null} $resource
 */
class VavooChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return [
            'id' => (string) $c['id'],
            'name' => (string) $c['name'],
            'category_id' => (string) $c['category_id'],
            'logo' => $c['logo'] === null ? null : (string) $c['logo'],
        ];
    }
}
