<?php

namespace App\Services;

use App\Models\User;
use App\Services\Accounts;
use App\Notifications\EmailLoginCode;
use App\Services\SocialLogin\SocialLogins;
use App\Notifications\EmailLoginLink;
use App\Notifications\EmailChangeLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final class EmailLogins
{
    public const LINK_TTL = 900;
    public const CODE_TTL = 300;
    public const OTP_TTL = 600;

    /** Send a six-digit, one-time code. Unknown addresses are handled identically. */
    public function sendCode(string $email): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put(self::otpKey($email), ['hash' => Hash::make($code)], self::OTP_TTL);
        Notification::route('mail', $email)->notify(new EmailLoginCode($code, self::OTP_TTL / 60));
    }

    /** Verify a code once; create an account only after the address has been proven. */
    public function verifyCode(string $email, string $code, Accounts $accounts): ?array
    {
        return Cache::lock('email-login:otp-lock:'.hash('sha256', mb_strtolower($email)), 5)
            ->block(2, function () use ($email, $code, $accounts): ?array {
                $key = self::otpKey($email);
                $entry = Cache::get($key);
                if (! is_array($entry) || ! is_string($entry['hash'] ?? null) || ! Hash::check($code, $entry['hash'])) {
                    return null;
                }

                Cache::forget($key);
                $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();
                $created = false;
                if ($user === null) {
                    $user = $accounts->create('Moi', $email, emailVerified: true);
                    $created = true;
                } elseif ($user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                return ['user' => $user, 'created' => $created];
            });
    }


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

    public function sendEmailChange(User $user, string $email, string $redirect, string $challenge): void
    {
        $token = Str::random(64);
        Cache::put(self::linkKey($token), [
            'user' => $user->id,
            'email' => $email,
            'type' => 'email_change',
            'redirect' => $redirect,
            'challenge' => $challenge,
        ], self::LINK_TTL);

        $url = route('v1.auth.email.consume', ['token' => $token]);
        $recipient = clone $user;
        $recipient->email = $email;
        $recipient->notify(new EmailChangeLink($url, self::LINK_TTL / 60, $user->name));
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

        if (($entry['type'] ?? null) === 'email_change') {
            $email = (string) ($entry['email'] ?? '');
            if ($email === '' || User::query()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
                return null;
            }

            $user->forceFill(['email' => $email, 'email_verified_at' => now()])->save();
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

    private static function otpKey(string $email): string
    {
        return 'email-login:otp:'.hash('sha256', mb_strtolower($email));
    }

    private static function codeKey(string $code): string
    {
        return 'email-login:code:'.hash('sha256', $code);
    }
}
