<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A list of a profile (docs/social.md): a top 10 (`top`, numbered, 10 titles at most) or a list
 * (`list`, in the chosen order, 100 at most), with its likes and comments. Written only through
 * App\Services\Social\Lists.
 */
class ProfileList extends Model
{
    public const TOP = 'top';

    public const LIST = 'list';

    public const MAX_TOP = 10;

    public const MAX_LIST = 100;

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

    public function maxItems(): int
    {
        return $this->kind === self::TOP ? self::MAX_TOP : self::MAX_LIST;
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
