<?php

namespace App\Services\SocialLogin;

/** Google or Apple, seen by App\Services\SocialLogin\SocialLogins. */
interface SocialProvider
{
    /** `google` or `apple`: the address of the routes. */
    public function name(): string;

    /** Shown by the apps: « Continuer avec Google ». */
    public function label(): string;

    /** Its settings are in the `.env`. */
    public function available(): bool;

    /** The provider's page where the person signs in, sending it back to [$callback]. */
    public function authorizationUrl(string $state, string $callback): string;

    /**
     * The person, from what the provider sent back to [$callback] (query or form fields).
     *
     * @param  array<string, mixed>  $input
     *
     * @throws SocialLoginFailed
     */
    public function identity(array $input, string $callback): SocialIdentity;
}
