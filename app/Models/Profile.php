<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

/**
 * A viewer profile ("Qui regarde ?"). Favorites, history and resume
 * positions will be attached to a profile, not to the account.
 */
#[Fillable(['name', 'avatar', 'is_kid', 'max_age_rating', 'allow_unrated', 'locked_sources', 'pin', 'position'])]
#[Hidden(['pin', 'sync_revision'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /** Maximum number of profiles per account. */
    public const MAX_PER_USER = 5;

    /** Age limit of a kid profile created without an explicit one. */
    public const DEFAULT_KID_MAX_AGE = 10;

    /** Fields guarded by the parental PIN once parental control is on. */
    public const RESTRICTION_FIELDS = ['is_kid', 'max_age_rating', 'allow_unrated', 'locked_sources'];

    protected $attributes = [
        'allow_unrated' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_kid' => 'boolean',
            'max_age_rating' => 'integer',
            'allow_unrated' => 'boolean',
            'locked_sources' => 'array',
            'pin' => 'hashed',
            'position' => 'integer',
            'sync_revision' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ProfileItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ProfileItem::class);
    }

    /** Age limit that applies to this profile, null = no limit. */
    public function maxAge(): ?int
    {
        return $this->max_age_rating ?? ($this->is_kid ? self::DEFAULT_KID_MAX_AGE : null);
    }

    /** Whether a title with this age rating (null = unknown) can be shown. */
    public function allowsAge(?int $ageRating): bool
    {
        $max = $this->maxAge();

        if ($max === null) {
            return true;
        }

        return $ageRating === null ? $this->allow_unrated : $ageRating <= $max;
    }

    /** @return list<string> */
    public function lockedSources(): array
    {
        return array_values(array_map(strval(...), $this->locked_sources ?? []));
    }

    public function isRestricted(): bool
    {
        return $this->maxAge() !== null;
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
