<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Google or Apple account linked to a Vixeo account (« Continuer avec Google / Apple »,
 * App\Services\SocialLogin\SocialLogins).
 */
#[Fillable(['user_id', 'provider', 'subject', 'email'])]
class SocialAccount extends Model
{
    /**
     * The providers linked to [$user], in the order they were linked: `["google"]`.
     *
     * @return list<string>
     */
    public static function providersOf(User $user): array
    {
        return array_values(array_unique(array_map('strval', self::query()->where('user_id', $user->id)
            ->orderBy('id')->pluck('provider')->all())));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
