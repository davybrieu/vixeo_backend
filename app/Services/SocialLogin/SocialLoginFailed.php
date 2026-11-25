<?php

namespace App\Services\SocialLogin;

use RuntimeException;

/** A sign-in with Google or Apple that can't go on: [$reason] goes back to the app (`?error=`). */
final class SocialLoginFailed extends RuntimeException
{
    /** The provider refused the code or couldn't be reached. */
    public const FAILED = 'failed';

    /** The provider gave no address. */
    public const NO_EMAIL = 'email';

    /** The address belongs to a Vixeo account, but the provider didn't check it. */
    public const UNVERIFIED = 'unverified';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
