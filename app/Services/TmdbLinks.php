<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\TmdbLink;
use App\Services\Tmdb\TmdbAccount;
use App\Services\Tmdb\TmdbRequestTokenExpired;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Links a TMDb account to a profile (docs/compte-tmdb.md). The app opens (or shows as a QR code)
 * TMDb's approval page, then polls until the person has approved: a session is created for this
 * profile only. A TMDb account is linked to one Vixeo profile at most. Once linked, the profile's
 * reviews are sent to TMDb (Vixeo wins), and the ratings TMDb has that Vixeo doesn't are brought in.
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
     * @return array{status: 'linked', link: TmdbLink}|array{status: 'pending'|'expired'|'taken'}
     */
    public function complete(Profile $profile): array
    {
        // One poll at a time per profile (TV and phone polling together…).
        $result = Cache::lock(self::key($profile).':lock', 15)->get(fn () => $this->tryComplete($profile));

        return is_array($result) ? $result : ['status' => 'pending'];
    }

    /** Removes the link and ends its TMDb session. The reviews stay: they belong to Vixeo. */
    public function unlink(Profile $profile): void
    {
        Cache::forget(self::key($profile));
        $link = $profile->tmdbLink;
        if ($link !== null) {
            $this->tmdb->deleteSession($link->session_id);
            $link->delete();
        }
        $this->ratings->unlinked($profile);
    }

    /** @return array{status: 'linked', link: TmdbLink}|array{status: 'pending'|'expired'|'taken'} */
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
        $accountId = (int) ($account['id'] ?? 0);

        // A TMDb account is linked to one Vixeo profile at most: this one is already linked
        // elsewhere. The new session is ended; the person unlinks it there first.
        $taken = TmdbLink::query()->where('account_id', $accountId)->where('profile_id', '!=', $profile->id)->exists();
        if ($taken) {
            $this->tmdb->deleteSession($session);
            Cache::forget(self::key($profile));

            return ['status' => 'taken'];
        }

        $previousSession = $link?->session_id;
        $avatar = $account['avatar']['tmdb']['avatar_path'] ?? null;

        $link = $profile->tmdbLink()->updateOrCreate([], [
            'account_id' => $accountId,
            'username' => (string) ($account['username'] ?? ''),
            'name' => is_string($account['name'] ?? null) && $account['name'] !== '' ? $account['name'] : null,
            'avatar_path' => is_string($avatar) && $avatar !== '' ? $avatar : null,
            'session_id' => $session,
            'linked_at' => now(),
        ]);

        // TMDb follows Vixeo: every review is sent (Vixeo wins), TMDb's other ratings come in.
        // TMDb unreachable: done at the next sync.
        try {
            $this->ratings->sync($profile, $link, all: true);
            Cache::put("tmdb-ratings:{$profile->id}:{$link->account_id}", true, TmdbRatings::STALE_AFTER);
        } catch (Throwable $e) {
            Log::warning('TMDb ratings not synced after linking', ['error' => $e::class]);
        }

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
