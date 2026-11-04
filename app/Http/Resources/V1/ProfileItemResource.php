<?php

namespace App\Http\Resources\V1;

use App\Models\ProfileItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProfileItem */
class ProfileItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'source' => $this->source,
            'item_id' => $this->item_id,
            'type' => $this->type,
            'title' => $this->title,
            'year' => $this->year,
            'poster_url' => $this->poster_url,
            'backdrop_url' => $this->backdrop_url,
            'parent_item_id' => $this->parent_item_id,
            'season_number' => $this->season_number,
            'episode_number' => $this->episode_number,

            'is_favorite' => $this->is_favorite,
            'favorited_at' => $this->favorited_at,
            'favorite_changed_at' => $this->favorite_changed_at,

            /** Resume position in milliseconds. */
            'position_ms' => $this->position_ms,
            'duration_ms' => $this->duration_ms,
            /** Between 0 and 1, null if the duration is unknown (progress bar). */
            'progress' => $this->progress(),
            /** Non-null = in "Continuer à regarder". */
            'last_engaged_at' => $this->last_engaged_at,
            'progress_changed_at' => $this->progress_changed_at,

            'is_watched' => $this->is_watched,
            'watched_at' => $this->watched_at,
            'watched_changed_at' => $this->watched_changed_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
