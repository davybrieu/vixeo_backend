<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An app that can receive push notifications (docs/notifications.md): one per signed-in app
 * (API token), removed with it. `target` says how it receives them: a Firebase registration token
 * or installation ID, kept encrypted in `token` and never sent back to the apps, or the real-time
 * channel of the apps without Firebase (Windows, no `token`). Written only through
 * App\Services\Push\PushDevices.
 */
class PushDevice extends Model
{
    /** Firebase registration token (apps before firebase_messaging 17). */
    public const FCM_TOKEN = 'token';

    /** Firebase installation ID (firebase_messaging 17: register() / onRegistered). */
    public const FCM_FID = 'fid';

    /** No Firebase (Windows): a Reverb event the app shows while it runs (App\Services\Push\RealtimePush). */
    public const REALTIME = 'realtime';

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

    /** Field of FCM's HTTP v1 message that targets this app: `fid` or (older apps) `token`. */
    public function fcmField(): string
    {
        return $this->target === self::FCM_FID ? 'fid' : 'token';
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
