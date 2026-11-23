<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a profile did (docs/social.md): a review, a watched title (a diary entry), a list or a
 * title liked (« J'aime », a synced item). Its followers see it in their « Fil d'actualité », and
 * the « Activité » tab of its page lists them with their date. Written only through
 * App\Services\Social\Activities.
 */
class SocialActivity extends Model
{
    public const UPDATED_AT = null;

    public const REVIEW = 'review';

    public const WATCHED = 'watched';

    public const LIST = 'list';

    public const LIKED = 'liked';

    protected $guarded = [];

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /** @return BelongsTo<TmdbRating, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(TmdbRating::class, 'tmdb_rating_id');
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo
    {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<ProfileList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ProfileList::class, 'profile_list_id');
    }

    /** The title liked (`liked`). @return BelongsTo<ProfileItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ProfileItem::class, 'profile_item_id');
    }
}
