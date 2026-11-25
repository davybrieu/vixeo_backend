<?php

namespace App\Services\SocialLogin;

use App\Support\Jwt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * « Continuer avec Apple » on the web (Android and Windows have no native « Sign in with
 * Apple »): a Services ID of the team, the code posted back to the API (`form_post`) and
 * exchanged with a client secret signed by a « Sign in with Apple » key (`services.apple`).
 */
final class AppleSignIn implements SocialProvider
{
    private const AUTHORIZE = 'https://appleid.apple.com/auth/authorize';

    private const TOKEN = 'https://appleid.apple.com/auth/token';

    private const ISSUER = 'https://appleid.apple.com';

    public function name(): string
    {
        return 'apple';
    }

    public function label(): string
    {
        return 'Apple';
    }

    public function available(): bool
    {
        return filled(config('services.apple.client_id')) && filled(config('services.apple.team_id'))
            && filled(config('services.apple.key_id')) && $this->privateKey() !== null;
    }

    public function authorizationUrl(string $state, string $callback): string
    {
        return self::AUTHORIZE.'?'.http_build_query([
            'client_id' => (string) config('services.apple.client_id'),
            'redirect_uri' => $callback,
            'response_type' => 'code',
            // Name and address are asked: Apple posts the result back (form_post).
            'response_mode' => 'form_post',
            'scope' => 'name email',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function identity(array $input, string $callback): SocialIdentity
    {
        $code = $input['code'] ?? null;
        if (! is_string($code) || $code === '') {
            throw new SocialLoginFailed(SocialLoginFailed::FAILED, 'No code.');
        }

        $clientId = (string) config('services.apple.client_id');
        try {
            $response = Http::asForm()->acceptJson()->timeout(10)->post(self::TOKEN, [
                'client_id' => $clientId,
                'client_secret' => $this->clientSecret(),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $callback,
            ]);
        } catch (ConnectionException) {
            throw new SocialLoginFailed(SocialLoginFailed::FAILED, 'Apple unreachable.');
        }

        // Straight from Apple's token endpoint over TLS: no signature to check (Jwt::unverifiedClaims).
        $claims = $response->successful() ? Jwt::unverifiedClaims($response->json('id_token')) : null;
        if ($claims === null
            || ($claims['iss'] ?? null) !== self::ISSUER
            || ($claims['aud'] ?? null) !== $clientId
            || (int) ($claims['exp'] ?? 0) < time()
            || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            Log::warning('Apple sign-in refused', ['status' => $response->status(), 'error' => $response->json('error')]);

            throw new SocialLoginFailed(SocialLoginFailed::FAILED, 'Apple refused the code.');
        }

        $email = is_string($claims['email'] ?? null) && $claims['email'] !== '' ? $claims['email'] : null;

        return new SocialIdentity(
            provider: 'apple',
            subject: $claims['sub'],
            email: $email,
            emailVerified: $email !== null && in_array($claims['email_verified'] ?? false, [true, 'true'], true),
            name: self::postedName($input['user'] ?? null),
        );
    }

    /** The client secret: a JWT of 5 minutes signed with the team's key (ES256). */
    private function clientSecret(): string
    {
        $now = time();

        try {
            return Jwt::es256([
                'iss' => (string) config('services.apple.team_id'),
                'iat' => $now,
                'exp' => $now + 300,
                'aud' => self::ISSUER,
                'sub' => (string) config('services.apple.client_id'),
            ], (string) $this->privateKey(), (string) config('services.apple.key_id'));
        } catch (RuntimeException $e) {
            Log::error('Apple sign-in key unusable', ['error' => $e->getMessage()]);

            throw new SocialLoginFailed(SocialLoginFailed::FAILED, 'Invalid Apple key.');
        }
    }

    /** The .p8 key: a path, "base64:…" or the PEM itself. */
    private function privateKey(): ?string
    {
        $value = trim((string) config('services.apple.private_key'));
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'base64:')) {
            $pem = base64_decode(substr($value, 7), true);
        } elseif (str_contains($value, '-----BEGIN')) {
            $pem = str_replace('\n', "\n", $value);
        } else {
            $pem = is_readable($value) ? file_get_contents($value) : false;
        }

        return is_string($pem) && str_contains($pem, 'PRIVATE KEY') ? $pem : null;
    }

    /** The name Apple posts at the first sign-in only (`user`: JSON). */
    private static function postedName(mixed $user): ?string
    {
        $data = is_string($user) ? json_decode($user, true) : (is_array($user) ? $user : null);
        $name = is_array($data) && is_array($data['name'] ?? null) ? $data['name'] : [];
        $full = trim(implode(' ', array_filter([
            is_string($name['firstName'] ?? null) ? trim($name['firstName']) : null,
            is_string($name['lastName'] ?? null) ? trim($name['lastName']) : null,
        ])));

        return $full !== '' ? $full : null;
    }
}
