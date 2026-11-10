<?php

namespace App\Http\Resources\V1\Playback;

use App\Models\ProfileStream;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProfileStream */
class StreamPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            /** Source of the content ("tmdb", "fast:pluto"…), same as the profile item. */
            'source' => $this->source,
            /** Id of the content inside that source ("movie:550"…). */
            'item_id' => $this->item_id,
            /** Stream chosen by the app inside the content ("vavoo:<channelId>", a player URL…). */
            'stream_id' => $this->stream_id,
            /** Resolved URL to replay it, null until it has worked once. */
            'stream_url' => $this->stream_url,
            'stream_type' => $this->stream_type,
            'extractor' => $this->extractor,
            'quality' => $this->quality,
            'language' => $this->language,
            'is_favorite' => $this->is_favorite,
            'favorited_at' => $this->favorited_at,
            /** Last time this stream played successfully (null = never). */
            'last_worked_at' => $this->last_worked_at,
        ];
    }
}
