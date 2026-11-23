<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A title of a list, with what the list shows of it (from TMDb when it was added). */
class ProfileListItem extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['position' => 'integer', 'tmdb_id' => 'integer', 'year' => 'integer'];
    }

    /** @return BelongsTo<ProfileList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ProfileList::class, 'profile_list_id');
    }

    /** `movie:550`, as everywhere in the API. */
    public function itemId(): string
    {
        return "{$this->media_type}:{$this->tmdb_id}";
    }
}
