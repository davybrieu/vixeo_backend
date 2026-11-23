<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A list of a profile (docs/social.md): a title, a description and up to 100 titles in the order
 * its author chose (a « Top 10 » is a list like the others), with its likes and comments. « À voir »
 * is not one of them: it is the `favorite` group of the synchronisation. Written only through
 * App\Services\Social\Lists.
 */
class ProfileList extends Model
{
    public const MAX_ITEMS = 100;

    public const MAX_TITLE = 100;

    public const MAX_DESCRIPTION = 1000;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'items_count' => 'integer',
            'published_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /** @return HasMany<ProfileListItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ProfileListItem::class)->orderBy('position');
    }

    /** @return HasMany<ListLike, $this> */
    public function likes(): HasMany
    {
        return $this->hasMany(ListLike::class);
    }

    /** @return HasMany<SocialComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(SocialComment::class);
    }
}
