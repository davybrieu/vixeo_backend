<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * A viewer profile ("Qui regarde ?"). Favorites, history and resume
 * positions will be attached to a profile, not to the account.
 */
#[Fillable(['name', 'avatar', 'is_kid', 'max_age_rating', 'pin', 'position'])]
#[Hidden(['pin'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /** Maximum number of profiles per account. */
    public const MAX_PER_USER = 5;

    protected function casts(): array
    {
        return [
            'is_kid' => 'boolean',
            'max_age_rating' => 'integer',
            'pin' => 'hashed',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasPin(): bool
    {
        return $this->pin !== null;
    }

    public function checkPin(string $pin): bool
    {
        return $this->hasPin() && Hash::check($pin, $this->pin);
    }
}
