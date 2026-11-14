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
            'name' => $this->name,\n            'username' => $this->username,\n            'bio' => $this->bio,
            /** Avatar id (GET /avatars), null = initial letter. */
            'avatar' => $this->avatar,
            'poster_format' => $this->poster_format,
            'is_kid' => $this->is_kid,
            'max_age_rating' => $this->max_age_rating,
            /** Age limit actually applied (kid profile without `max_age_rating`: 10). Null = no limit. */
            'effective_max_age_rating' => $this->maxAge(),
            'allow_unrated' => $this->allow_unrated,
            /** Sources / folders the app must hide on this profile. */
            'locked_sources' => $this->lockedSources(),
            'has_pin' => $this->hasPin(),
            /** Preferred audio language, null = original version. */
            'audio_language' => $this->audio_language,
            /** Preferred subtitles, null = none. */
            'subtitle_language' => $this->subtitle_language,
            /** Hide episode artwork, titles and descriptions until selected. */
            'no_spoiler' => (bool) $this->no_spoiler,
            /** Segments the player skips by itself (TheIntroDB timestamps, see GET …/segments). */
            'skip_intro' => (bool) $this->skip_intro,
            'skip_recap' => (bool) $this->skip_recap,
            'skip_credits' => (bool) $this->skip_credits,
            'skip_preview' => (bool) $this->skip_preview,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
