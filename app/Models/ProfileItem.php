<?php

namespace App\Models;

use App\Enums\ItemType;
use Database\Factories\ProfileItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Synced state of one content for one profile (favorite, progress, watched).
 * Written only through App\Services\ProfileSync.
 */
#[Fillable(['source', 'item_id', 'type', 'title', 'year', 'poster_url', 'backdrop_url', 'parent_item_id', 'season_number', 'episode_number'])]
class ProfileItem extends Model
{
    /** @use HasFactory<ProfileItemFactory> */
    use HasFactory;

    protected $attributes = [
        'is_favorite' => false,
        'position_ms' => 0,
        'is_watched' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'year' => 'integer',
            'season_number' => 'integer',
            'episode_number' => 'integer',
            'is_favorite' => 'boolean',
            'favorited_at' => 'datetime',
            'favorite_changed_at' => 'datetime',
            'position_ms' => 'integer',
            'duration_ms' => 'integer',
            'last_engaged_at' => 'datetime',
            'progress_changed_at' => 'datetime',
            'is_watched' => 'boolean',
            'watched_at' => 'datetime',
            'watched_changed_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /** Items that carry some state (an item can be fully reset: not favorite, no progress, not watched). */
    #[Scope]
    protected function notEmpty(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('is_favorite', true)
            ->orWhere('is_watched', true)
            ->orWhere('position_ms', '>', 0)
            ->orWhereNotNull('last_engaged_at'));
    }

    /** "Continuer à regarder": engaged, not finished, most recent first. */
    #[Scope]
    protected function continueWatching(Builder $query): void
    {
        $query->whereNotNull('last_engaged_at')
            ->where('is_watched', false)
            ->orderByDesc('last_engaged_at')
            ->orderByDesc('id');
    }

    /** Playback progress between 0 and 1, if the duration is known. */
    public function progress(): ?float
    {
        return $this->duration_ms ? min(1.0, round($this->position_ms / $this->duration_ms, 3)) : null;
    }
}
