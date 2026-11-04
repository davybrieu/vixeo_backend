<?php

namespace App\Http\Resources\V1;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Profile */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'is_kid' => $this->is_kid,
            'max_age_rating' => $this->max_age_rating,
            /** Age limit actually applied (kid profile without `max_age_rating`: 10). Null = no limit. */
            'effective_max_age_rating' => $this->maxAge(),
            'allow_unrated' => $this->allow_unrated,
            /** Sources / folders the app must hide on this profile. */
            'locked_sources' => $this->lockedSources(),
            'has_pin' => $this->hasPin(),
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
