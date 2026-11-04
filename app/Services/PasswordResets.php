<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * "Mot de passe oublié": a 6-digit code sent by e-mail, typed in the app with the new password
 * (no link: works the same on phones, TVs and the web, and no secret ever travels in a URL).
 *
 * The code is hashed in `password_reset_tokens`, valid 15 minutes, single use, and 5 wrong
 * guesses invalidate it. See docs/compte.md.
 */
class PasswordResets
{
    public const TTL_MINUTES = 15;

    private const MAX_ATTEMPTS = 5;

    /** Codes e-mailed to one address per TTL (limits mail bombing). */
    private const MAX_SENDS = 3;

    /**
     * Sends a code if an account uses this address. Always silent, so that the answer
     * doesn't tell whether the address has an account.
     */
    public function send(string $email): void
    {
        $user = User::where('email', $email)->first();
        if ($user === null) {
            return;
        }

        $sendKey = 'password-reset-send:'.sha1($user->email);
        if (RateLimiter::tooManyAttempts($sendKey, self::MAX_SENDS)) {
            return;
        }
        RateLimiter::hit($sendKey, self::TTL_MINUTES * 60);

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($code), 'created_at' => now()],
        );
        RateLimiter::clear($this->attemptsKey($user->email));

        $user->notify(new PasswordResetCode($code, self::TTL_MINUTES));
    }

    /**
     * Sets the new password if the code is right, then revokes every API token of the account.
     *
     * @throws ValidationException wrong, expired or used code (on `code`)
     */
    public function reset(string $email, string $code, string $password): User
    {
        $attemptsKey = $this->attemptsKey($email);

        if (RateLimiter::tooManyAttempts($attemptsKey, self::MAX_ATTEMPTS)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            throw ValidationException::withMessages(['code' => [__('Too many wrong codes. Ask for a new code.')]]);
        }

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();
        $user = User::where('email', $email)->first();
        $fresh = $row !== null && now()->subMinutes(self::TTL_MINUTES)->lt($row->created_at);

        if ($user === null || ! $fresh || ! Hash::check($code, $row->token)) {
            RateLimiter::hit($attemptsKey, self::TTL_MINUTES * 60);

            throw ValidationException::withMessages(['code' => [__('This reset code is incorrect or has expired.')]]);
        }

        DB::transaction(function () use ($user, $password) {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });
        RateLimiter::clear($attemptsKey);

        return $user;
    }

    private function attemptsKey(string $email): string
    {
        return 'password-reset-attempts:'.sha1(mb_strtolower($email));
    }
}
