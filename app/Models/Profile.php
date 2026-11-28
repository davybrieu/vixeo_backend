<?php

namespace App\Models;

use App\Services\ProfileCovers;
use App\Services\ProfilePhotos;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Compatibility model for the account's social identity. It uses the users table directly. */
#[Fillable([
    'name', 'username', 'bio', 'is_private', 'social_links', 'avatar', 'photo_path', 'cover',
    'cover_path', 'position', 'audio_language', 'subtitle_language', 'no_spoiler', 'skip_intro',
    'skip_recap', 'skip_credits', 'skip_preview', 'rate_after_watching', 'rating_source',
    'onboarded_at', 'registration_completed_at', 'suspended_at',
])]
#[Hidden(['sync_revision'])]
class Profile extends User
{
    public const MAX_PER_USER = 1;
    public const BIO_MAX_LENGTH = 160;
    public const SKIP_FIELDS = ['skip_intro', 'skip_recap', 'skip_credits', 'skip_preview'];

    protected $table = 'users';

    /**
     * Factories and legacy callers may still attach a Profile to an existing User. Since both are
     * now the same row, treat that insert as an update of the already-existing account.
     */
    protected function performInsert(Builder $query): bool
    {
        if ($this->getKey() !== null && static::query()->whereKey($this->getKey())->exists()) {
            $attributes = $this->getAttributes();
            unset($attributes['id'], $attributes['email'], $attributes['email_verified_at'], $attributes['remember_token']);

            $existing = static::query()->findOrFail($this->getKey());
            $this->setRawAttributes($existing->getAttributes(), true);
            $this->forceFill($attributes);
            $this->exists = true;

            return $this->performUpdate($query);
        }

        return parent::performInsert($query);
    }

    /** Legacy attribute retained for code that still reads profile.user_id. */
    public function getUserIdAttribute(): int
    {
        return (int) $this->id;
    }

    /** A profile is the account row itself. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }

    /** Accounts this account follows. */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'profile_follows', 'follower_profile_id', 'followed_profile_id')
            ->withTimestamps()->wherePivotNotNull('accepted_at');
    }

    /** Accounts following this account. */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'profile_follows', 'followed_profile_id', 'follower_profile_id')
            ->withTimestamps()->wherePivotNotNull('accepted_at');
    }

    public function items(): HasMany { return $this->hasMany(ProfileItem::class); }
    public function searches(): HasMany { return $this->hasMany(ProfileSearch::class); }
    public function episodeNotifications(): HasMany { return $this->hasMany(EpisodeNotification::class); }
    public function tmdbRatings(): HasMany { return $this->hasMany(TmdbRating::class); }
    public function lists(): HasMany { return $this->hasMany(ProfileList::class); }
    public function diaryEntries(): HasMany { return $this->hasMany(DiaryEntry::class); }

    public function isSocial(): bool
    {
        return $this->username !== null && $this->suspended_at === null;
    }

    public function photoUrl(): ?string
    {
        return ProfilePhotos::url($this->photo_path);
    }

    public function coverUrl(): ?string
    {
        return ProfileCovers::url($this->cover_path);
    }
}
