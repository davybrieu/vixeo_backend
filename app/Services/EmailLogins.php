<?php

namespace App\Services;

use App\Models\User;
use App\Services\SocialLogin\SocialLogins;
use App\Notifications\EmailLoginLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class EmailLogins
{
    public const LINK_TTL = 900;
    public const CODE_TTL = 300;

    public function send(User $user, string $redirect, string $challenge): void
    {
        $token = Str::random(64);
        Cache::put(self::linkKey($token), [
            'user' => $user->id,
            'redirect' => $redirect,
            'challenge' => $challenge,
        ], self::LINK_TTL);

        $url = route('v1.auth.email.consume', ['token' => $token]);
        $user->notify(new EmailLoginLink($url, self::LINK_TTL / 60));
    }

    /** Consume the emailed link once and return the app redirect with a short-lived PKCE code. */
    public function consume(string $token): ?string
    {
        $entry = Cache::pull(self::linkKey($token));
        if (! is_array($entry)) {
            return null;
        }

        $user = User::query()->find($entry['user'] ?? null);
        if ($user === null) {
            return null;
        }

        $code = Str::random(48);
        Cache::put(self::codeKey($code), [
            'user' => $user->id,
            'challenge' => (string) ($entry['challenge'] ?? ''),
        ], self::CODE_TTL);

        return (string) $entry['redirect'].'?'.http_build_query(['code' => $code], '', '&', PHP_QUERY_RFC3986);
    }

    public function redeem(string $code, string $verifier): ?User
    {
        $entry = Cache::pull(self::codeKey($code));
        if (! is_array($entry)
            || ! hash_equals((string) ($entry['challenge'] ?? ''), SocialLogins::challenge($verifier))) {
            return null;
        }

        $user = User::query()->find($entry['user'] ?? null);
        if ($user !== null && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    private static function linkKey(string $token): string
    {
        return 'email-login:link:'.hash('sha256', $token);
    }

    private static function codeKey(string $code): string
    {
        return 'email-login:code:'.hash('sha256', $code);
    }
}
