<?php

namespace App\Http\Resources\V1\Playback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StreamSourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this['provider'] ?? '',
            'name' => $this['name'] ?? '',
            'url' => $this['url'] ?? '',
        ];
    }
}
