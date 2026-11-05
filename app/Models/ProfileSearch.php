<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recent search of a profile, synced between its devices (docs/lecture.md).
 */
#[Fillable(['query', 'query_key'])]
class ProfileSearch extends Model
{
    /** Searches kept per profile. */
    public const MAX_PER_PROFILE = 20;

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public static function keyOf(string $query): string
    {
        return mb_strtolower(trim($query));
    }
}
