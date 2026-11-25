<?php

namespace App\Services\SocialLogin;

/** What Google or Apple says about the person who signed in. */
final readonly class SocialIdentity
{
    public function __construct(
        /** `google` or `apple`. */
        public string $provider,
        /** The provider's identifier of the person: it never changes, unlike the address. */
        public string $subject,
        public ?string $email,
        /** The provider checked that the address is the person's. */
        public bool $emailVerified,
        /** Apple gives it at the first sign-in only. */
        public ?string $name,
    ) {}

    /** An Apple relay address (« Masquer mon adresse e-mail »). */
    public function isRelayEmail(): bool
    {
        return $this->email !== null && str_ends_with(mb_strtolower($this->email), '@privaterelay.appleid.com');
    }
}
