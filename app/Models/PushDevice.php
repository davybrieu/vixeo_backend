<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An app that can receive push notifications (docs/notifications.md): one per signed-in app
 * (API token), removed with it. The Firebase registration token is encrypted and never sent
 * back to the apps. Written only through App\Services\Push\PushDevices.
 */
class PushDevice extends Model
{
    protected $guarded = [];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'new_episodes' => 'boolean',
            'social' => 'boolean',
            'new_episodes_since' => 'datetime',
            'utc_offset' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
