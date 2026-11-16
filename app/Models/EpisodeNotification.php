<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * An episode already announced to a profile: never twice (App\Services\Push\EpisodePush).
 */
class EpisodeNotification extends Model
{
    use Prunable;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'show_id' => 'integer',
            'season_number' => 'integer',
            'episode_number' => 'integer',
            'available_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** Kept 90 days (an episode is only announced in the 2 days after it comes out), `model:prune`. */
    public function prunable(): Builder
    {
        return static::where('sent_at', '<', now()->subDays(90));
    }
}
