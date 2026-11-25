<?php

namespace App\Services\SocialLogin;

use App\Support\Jwt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * « Continuer avec Google »: OpenID Connect, authorization code exchanged by the API (a « Web
 * application » client of Google Cloud, `services.google`).
 */
final class GoogleSignIn implements SocialProvider
{
    private const AUTHORIZE = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN = 'https://oauth2.googleapis.com/token';

    private const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    public function name(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google';
    }

    public function available(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function authorizationUrl(string $state, string $callback): string
    {
        return self::AUTHORIZE.'?'.http_build_query([
            'client_id' => (string) config('services.google.client_id'),
            'redirect_uri' => $callback,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            // The account chooser, even with one Google account in the browser.
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function identity(array $input, string $callback): SocialIdentity
    {
        $code = $input['code'] ?? null;
        if (! is_string($code) || $code === '') {
            throw new SocialLoginFailed(SocialLoginFailed::FAILED, 'No code.');
        }

        try {
            $response = Http::asForm()->acceptJson()->timeout(10)->post(self::TOKEN, [
                'code' => $code,
                'client_id' => (string) config('services.google.client_id'),
                'client_secret' => (string) config('services.google.client_secret'),
                'redirect_uri' => $callback,
                'grant_type' => 'authorization_code',
            ]);
        } catch (ConnectionException) {
            throw new SocialLoginFailed(SocialLoginFailed::FAILED, 'Google unreachable.');
        }

        // Straight from Google's token endpoint over TLS: no signature to check (Jwt::unverifiedClaims).
        $claims = $response->successful() ? Jwt::unverifiedClaims($response->json('id_token')) : null;
        if ($claims === null
            || ! in_array($claims['iss'] ?? null, self::ISSUERS, true)
            || ($claims['aud'] ?? null) !== (string) config('services.google.client_id')
            || (int) ($claims['exp'] ?? 0) < time()
            || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            Log::warning('Google sign-in refused', ['status' => $response->status(), 'error' => $response->json('error')]);

            throw new SocialLoginFailed(SocialLoginFailed::FAILED, 'Google refused the code.');
        }

        $email = is_string($claims['email'] ?? null) && $claims['email'] !== '' ? $claims['email'] : null;
        $name = $claims['name'] ?? $claims['given_name'] ?? null;

        return new SocialIdentity(
            provider: 'google',
            subject: $claims['sub'],
            email: $email,
            emailVerified: $email !== null && in_array($claims['email_verified'] ?? false, [true, 'true'], true),
            name: is_string($name) && trim($name) !== '' ? trim($name) : null,
        );
    }
}
