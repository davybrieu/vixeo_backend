<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** « J'aime » on a list (docs/social.md). */
class ListLike extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return BelongsTo<ProfileList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ProfileList::class, 'profile_list_id');
    }
}
