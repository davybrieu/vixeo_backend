<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TMDb account linked to a profile (docs/compte-tmdb.md): its ratings are read and written with
 * this link's own TMDb session, stored encrypted and never sent to the apps.
 */
#[Fillable(['account_id', 'username', 'name', 'avatar_path', 'session_id', 'linked_at'])]
#[Hidden(['session_id'])]
class TmdbLink extends Model
{
    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'session_id' => 'encrypted',
            'linked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
