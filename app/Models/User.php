<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'username', 'bio', 'is_private', 'social_links', 'avatar', 'photo_path',
    'cover', 'cover_path', 'position', 'sync_revision', 'audio_language', 'subtitle_language',
    'no_spoiler', 'skip_intro', 'skip_recap', 'skip_credits', 'skip_preview',
    'rate_after_watching', 'rating_source', 'onboarded_at', 'registration_completed_at',
    'suspended_at',
])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_private' => 'boolean',
            'social_links' => 'array',
            'position' => 'integer',
            'sync_revision' => 'integer',
            'no_spoiler' => 'boolean',
            'skip_intro' => 'boolean',
            'skip_recap' => 'boolean',
            'skip_credits' => 'boolean',
            'skip_preview' => 'boolean',
            'rate_after_watching' => 'boolean',
            'onboarded_at' => 'datetime',
            'registration_completed_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /** Apps of the account that can receive push notifications. @return HasMany<PushDevice, $this> */
    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class, 'profile_id');
    }

    /**
     * Backward-compatible relation name for the API layer. The related Profile model is now the
     * same users row (same primary key), not a record in a profiles table.
     *
     * @return HasMany<Profile, $this>
     */
    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'id', 'id');
    }
}
