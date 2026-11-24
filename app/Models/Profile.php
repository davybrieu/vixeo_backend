<?php

namespace App\Models;

use App\Enums\PosterFormat;
use App\Services\ProfileCovers;
use App\Services\ProfilePhotos;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

/**
 * A viewer profile ("Qui regarde ?"). Favorites, history and resume
 * positions will be attached to a profile, not to the account.
 */
#[Fillable([
    'name', 'username', 'bio', 'is_private', 'social_links', 'avatar', 'cover', 'poster_format', 'is_kid', 'max_age_rating', 'allow_unrated', 'locked_sources', 'pin', 'position',
    'audio_language', 'subtitle_language', 'no_spoiler', 'skip_intro', 'skip_recap', 'skip_credits', 'skip_preview',
    'rate_after_watching', 'onboarded_at',
])]
#[Hidden(['pin', 'sync_revision'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /** Maximum number of profiles per account. */
    public const MAX_PER_USER = 5;

    /** Longest biography of a public profile, in characters (developer's request, 21/11/2026). */
    public const BIO_MAX_LENGTH = 160;

    /** Age limit of a kid profile created without an explicit one. */
    public const DEFAULT_KID_MAX_AGE = 10;

    /** Fields guarded by the parental PIN once parental control is on. */
    public const RESTRICTION_FIELDS = ['is_kid', 'max_age_rating', 'allow_unrated', 'locked_sources'];

    /** Playback settings: segments the player skips by itself (else it shows "Passer"). */
    public const SKIP_FIELDS = ['skip_intro', 'skip_recap', 'skip_credits', 'skip_preview'];

    protected $attributes = [
        'allow_unrated' => false,
        'poster_format' => 'portrait',
        'no_spoiler' => false,
        'skip_intro' => false,
        'skip_recap' => false,
        'skip_credits' => false,
        'skip_preview' => false,
        'rate_after_watching' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_kid' => 'boolean',
            'is_private' => 'boolean',
            'social_links' => 'array',
            'max_age_rating' => 'integer',
            'allow_unrated' => 'boolean',
            'poster_format' => PosterFormat::class,
            'locked_sources' => 'array',
            'pin' => 'hashed',
            'position' => 'integer',
            'sync_revision' => 'integer',
            'no_spoiler' => 'boolean',
            'skip_intro' => 'boolean',
            'skip_recap' => 'boolean',
            'skip_credits' => 'boolean',
            'skip_preview' => 'boolean',
            'rate_after_watching' => 'boolean',
            'onboarded_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Profiles this profile follows. */
    /** @return BelongsToMany<Profile, $this> */
    public function following(): BelongsToMany
    {
        // Accepted follows only: a request to a private account waits (App\Services\Social\Follows).
        return $this->belongsToMany(self::class, 'profile_follows', 'follower_profile_id', 'followed_profile_id')
            ->withTimestamps()->wherePivotNotNull('accepted_at');
    }

    /** Profiles following this profile. */
    /** @return BelongsToMany<Profile, $this> */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'profile_follows', 'followed_profile_id', 'follower_profile_id')
            ->withTimestamps()->wherePivotNotNull('accepted_at');
    }

    /** @return HasMany<ProfileItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ProfileItem::class);
    }

    /** @return HasMany<ProfileSearch, $this> */
    public function searches(): HasMany
    {
        return $this->hasMany(ProfileSearch::class);
    }

    /** Apps where this profile is in use, for push notifications. @return HasMany<PushDevice, $this> */
    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }

    /** Episodes already announced to this profile. @return HasMany<EpisodeNotification, $this> */
    public function episodeNotifications(): HasMany
    {
        return $this->hasMany(EpisodeNotification::class);
    }

    /** The profile's reviews (« Mes avis »): rating and comment. @return HasMany<TmdbRating, $this> */
    public function tmdbRatings(): HasMany
    {
        return $this->hasMany(TmdbRating::class);
    }

    /** The profile's lists. @return HasMany<ProfileList, $this> */
    public function lists(): HasMany
    {
        return $this->hasMany(ProfileList::class);
    }

    /** What the profile watched, day by day (« Journal »). @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }

    /** An adult profile with a username, not suspended: it has a public page and social features. */
    public function isSocial(): bool
    {
        return ! $this->is_kid && $this->username !== null && $this->suspended_at === null;
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

    /** Address of its own photo (App\Services\ProfilePhotos), null = its avatar is shown. */
    public function photoUrl(): ?string
    {
        return ProfilePhotos::url($this->photo_path);
    }

    /** Address of its own cover (App\Services\ProfileCovers), null = its cover of the catalog is shown. */
    public function coverUrl(): ?string
    {
        return ProfileCovers::url($this->cover_path);
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
