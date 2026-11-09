<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\TmdbLink;
use App\Services\Tmdb\TmdbAccount;
use App\Services\Tmdb\TmdbRequestTokenExpired;
use Illuminate\Support\Facades\Cache;

/**
 * Links a TMDb account to a profile (docs/compte-tmdb.md). The app opens (or shows as a QR code)
 * TMDb's approval page, then polls until the person has approved: a session is created for this
 * profile only. The same TMDb account can be linked to other profiles, of any Vixeo account.
 */
class TmdbLinks
{
    /** Seconds between two polls of the app. */
    public const INTERVAL_SECONDS = 5;

    /** Once linked, late polls (another device, a retry) still get the link. */
    private const LINKED = 'linked';

    public function __construct(private TmdbAccount $tmdb, private TmdbRatings $ratings) {}

    /**
     * Starts a link: the page where the person approves Vixeo on TMDb.
     *
     * @return array{authorize_url: string, expires_in: int}
     */
    public function start(Profile $profile): array
    {
        ['request_token' => $token, 'expires_at' => $expiresAt] = $this->tmdb->requestToken();
        $ttl = (int) max(60, min(3600, now()->diffInSeconds($expiresAt, false)));
        Cache::put(self::key($profile), $token, $ttl);

        return [
            'authorize_url' => rtrim((string) config('services.tmdb.authenticate_url'), '/').'/'.rawurlencode($token),
            'expires_in' => $ttl,
        ];
    }

    /**
     * Finishes the link once the person approved: 'pending' until then, 'expired' when the
     * approval page is no longer valid (start again).
     *
     * @return array{status: 'linked', link: TmdbLink}|array{status: 'pending'|'expired'}
     */
    public function complete(Profile $profile): array
    {
        // One poll at a time per profile (TV and phone polling together…).
        $result = Cache::lock(self::key($profile).':lock', 15)->get(fn () => $this->tryComplete($profile));

        return is_array($result) ? $result : ['status' => 'pending'];
    }

    /** Removes the link, ends its TMDb session and forgets the ratings' copy (comments included). */
    public function unlink(Profile $profile): void
    {
        Cache::forget(self::key($profile));
        $link = $profile->tmdbLink;
        if ($link !== null) {
            $this->tmdb->deleteSession($link->session_id);
            $link->delete();
        }
        // The copy of the ratings and the comments: Vixeo keeps nothing of an unlinked account.
        $this->ratings->forget($profile);
    }

    /** @return array{status: 'linked', link: TmdbLink}|array{status: 'pending'|'expired'} */
    private function tryComplete(Profile $profile): array
    {
        $token = Cache::get(self::key($profile));
        $link = $profile->tmdbLink()->first();

        if ($token === self::LINKED && $link !== null) {
            return ['status' => 'linked', 'link' => $link];
        }
        if (! is_string($token) || $token === self::LINKED) {
            return ['status' => 'expired'];
        }

        try {
            $session = $this->tmdb->createSession($token);
        } catch (TmdbRequestTokenExpired) {
            Cache::forget(self::key($profile));

            return ['status' => 'expired'];
        }

        if ($session === null) {
            return ['status' => 'pending'];
        }

        $account = $this->tmdb->account($session);
        $previousSession = $link?->session_id;
        $avatar = $account['avatar']['tmdb']['avatar_path'] ?? null;

        $link = $profile->tmdbLink()->updateOrCreate([], [
            'account_id' => (int) ($account['id'] ?? 0),
            'username' => (string) ($account['username'] ?? ''),
            'name' => is_string($account['name'] ?? null) && $account['name'] !== '' ? $account['name'] : null,
            'avatar_path' => is_string($avatar) && $avatar !== '' ? $avatar : null,
            'session_id' => $session,
            'linked_at' => now(),
        ]);

        // Another TMDb account: the copy of the previous one's ratings goes.
        $this->ratings->forget($profile, keepAccountId: $link->account_id);

        // Linked again (another TMDb account…): the old session is no longer used.
        if ($previousSession !== null && $previousSession !== $session) {
            $this->tmdb->deleteSession($previousSession);
        }

        Cache::put(self::key($profile), self::LINKED, 600);

        return ['status' => 'linked', 'link' => $link];
    }

    private static function key(Profile $profile): string
    {
        return 'tmdb-link:'.$profile->id;
    }
}
