<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * A stream a player reported as dead, kept 24 h. Written only through
 * App\Services\Playback\DeadStreams. See docs/lecture.md.
 */
class DeadStream extends Model
{
    use Prunable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dead_at' => 'datetime',
        ];
    }

    /** Rows older than a day are deleted by `php artisan model:prune` (scheduled daily). */
    public function prunable(): Builder
    {
        return static::where('dead_at', '<', now()->subDay());
    }
}
