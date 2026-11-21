<?php

namespace App\Http\Resources\V1;

use App\Models\PushDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PushDevice */
class PushDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'platform' => (string) $this->platform,
            /** Profile whose new episodes come to this app. */
            'profile_id' => $this->profile_id === null ? null : (int) $this->profile_id,
            'new_episodes' => (bool) $this->new_episodes,
            /** Likes of the profile's reviews, its followers and follow requests. */
            'social' => (bool) $this->social,
            'registered_at' => $this->created_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
