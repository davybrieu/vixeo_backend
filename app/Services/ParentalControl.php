<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Account-wide parental PIN. Wrong guesses are limited per account, whichever
 * endpoint they come from (verify, profile changes…).
 */
class ParentalControl
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    /**
     * Ensure $pin is the account's parental PIN, if parental control is on.
     *
     * @throws ValidationException missing or wrong PIN (on the given field)
     * @throws ThrottleRequestsException too many wrong PINs
     */
    public function authorize(User $user, ?string $pin, string $field = 'parental_pin'): void
    {
        if (! $user->hasParentalControl()) {
            return;
        }

        if ($pin === null || $pin === '') {
            throw ValidationException::withMessages([$field => [__('The parental PIN is required.')]]);
        }

        if (! $this->check($user, $pin)) {
            throw ValidationException::withMessages([$field => [__('Incorrect parental PIN.')]]);
        }
    }

    /** @throws ThrottleRequestsException */
    public function check(User $user, string $pin): bool
    {
        $key = 'parental-pin:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw new ThrottleRequestsException(headers: ['Retry-After' => $seconds]);
        }

        if ($user->parental_pin !== null && Hash::check($pin, $user->parental_pin)) {
            RateLimiter::clear($key);

            return true;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        return false;
    }

    public function setPin(User $user, ?string $pin): void
    {
        $user->forceFill(['parental_pin' => $pin])->save();
    }
}
