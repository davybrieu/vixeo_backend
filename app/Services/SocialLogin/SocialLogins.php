<?php

namespace App\Services\SocialLogin;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Accounts;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * « Continuer avec Google / Apple » (docs/connexion-sociale.md), for apps that run the sign-in
 * in a browser (RFC 8252, « OAuth 2.0 for Native Apps »):
 *
 * 1. the app opens `auth/social/{provider}` in a browser, with where to come back (its own
 *    scheme `app.vixeo://auth`, or a loopback address on Windows) and the hash of a secret it
 *    keeps (PKCE, `code_challenge`);
 * 2. the API sends the browser to Google or Apple, and they send it back to the API with a code
 *    (the API is the OAuth client: no secret in the apps);
 * 3. the API finds or creates the account, then sends the browser back to the app with a code of
 *    its own, valid 5 minutes, once;
 * 4. the app trades this code and its secret for a token (`auth/social/token`).
 *
 * Someone who gets the code (another app on the device, a link sent to someone else) can't use
 * it without the secret, which never left the app that started.
 */
final class SocialLogins
{
    /** Seconds to sign in at Google or Apple. */
    public const STATE_TTL = 600;

    /** Seconds for the app to trade its code. */
    public const CODE_TTL = 300;

    /** Where Android and iOS come back: the app's scheme. */
    public const APP_REDIRECT = 'app.vixeo://auth';

    /** Where Windows comes back: a server of the app on the device itself, on any port. */
    private const LOOPBACK_REDIRECT = '~^http://127\.0\.0\.1:([1-9][0-9]{3,4})/auth$~';

    /** @var array<string, SocialProvider> */
    private readonly array $providers;

    public function __construct(
        GoogleSignIn $google,
        AppleSignIn $apple,
        private readonly Accounts $accounts,
    ) {
        $this->providers = ['google' => $google, 'apple' => $apple];
    }

    /** @return array<string, SocialProvider> */
    public function providers(): array
    {
        return $this->providers;
    }

    public function provider(string $name): SocialProvider
    {
        return $this->providers[$name] ?? throw new NotFoundHttpException;
    }

    /** An address an app can come back to: never a website. */
    public static function validRedirect(string $uri): bool
    {
        if ($uri === self::APP_REDIRECT) {
            return true;
        }

        return preg_match(self::LOOPBACK_REDIRECT, $uri, $match) === 1 && (int) $match[1] <= 65535;
    }

    /** The S256 challenge of a PKCE verifier (RFC 7636). */
    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** Where the browser that starts goes: the provider, or back to the app when it isn't set up. */
    public function start(SocialProvider $provider, string $redirect, string $challenge): string
    {
        if (! $provider->available()) {
            return self::back($redirect, ['error' => 'unavailable']);
        }

        $state = Str::random(40);
        Cache::put(self::stateKey($state), [
            'provider' => $provider->name(),
            'redirect' => $redirect,
            'challenge' => $challenge,
        ], self::STATE_TTL);

        return $provider->authorizationUrl($state, $this->callbackUrl($provider));
    }

    /**
     * Where the browser that comes back from the provider goes: the app, with a code or an
     * error. Null: no sign-in waits for it (expired, already used, or never started here).
     *
     * @param  array<string, mixed>  $input  the query (Google) or the form (Apple)
     */
    public function finish(SocialProvider $provider, array $input): ?string
    {
        $state = $input['state'] ?? null;
        $pending = is_string($state) && $state !== '' ? Cache::pull(self::stateKey($state)) : null;
        if (! is_array($pending) || ($pending['provider'] ?? null) !== $provider->name()) {
            return null;
        }

        $redirect = (string) $pending['redirect'];
        // Google: `access_denied`; Apple: `user_cancelled_authorize`.
        if (filled($input['error'] ?? null)) {
            return self::back($redirect, ['error' => 'cancelled']);
        }

        try {
            $identity = $provider->identity($input, $this->callbackUrl($provider));
            [$user, $created, $passwordRemoved] = $this->account($identity);
        } catch (SocialLoginFailed $e) {
            return self::back($redirect, ['error' => $e->reason]);
        }

        $code = Str::random(48);
        Cache::put(self::codeKey($code), [
            'user' => $user->id,
            'challenge' => (string) $pending['challenge'],
            'created' => $created,
            'password_removed' => $passwordRemoved,
        ], self::CODE_TTL);

        return self::back($redirect, ['code' => $code]);
    }

    /**
     * The account of a code given back to the app: once, with the verifier of its challenge.
     * Null when the code is unknown, expired, already used or the verifier doesn't match.
     *
     * @return array{user: User, created: bool, password_removed: bool}|null
     */
    public function redeem(string $code, string $verifier): ?array
    {
        $entry = Cache::pull(self::codeKey($code));
        if (! is_array($entry) || ! hash_equals((string) ($entry['challenge'] ?? ''), self::challenge($verifier))) {
            return null;
        }

        $user = User::query()->find($entry['user'] ?? null);

        return $user === null ? null : [
            'user' => $user,
            'created' => (bool) ($entry['created'] ?? false),
            'password_removed' => (bool) ($entry['password_removed'] ?? false),
        ];
    }

    /**
     * The Vixeo account of [$identity]: the one already linked; else the one with its address,
     * linked now; else a new one.
     *
     * @return array{User, bool, bool} the account, whether it was created, legacy password_removed flag (always false)
     *
     * @throws SocialLoginFailed
     */
    private function account(SocialIdentity $identity): array
    {
        return DB::transaction(function () use ($identity) {
            $linked = SocialAccount::query()->with('user')
                ->where('provider', $identity->provider)
                ->where('subject', $identity->subject)
                ->first();
            if ($linked !== null) {
                if ($identity->email !== null && $identity->email !== $linked->email) {
                    $linked->update(['email' => $identity->email]);
                }

                return [$linked->user, false, false];
            }

            if ($identity->email === null) {
                throw new SocialLoginFailed(SocialLoginFailed::NO_EMAIL);
            }

            $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($identity->email)])->first();
            $created = false;
            $passwordRemoved = false;
            if ($user !== null) {
                if (! $identity->emailVerified) {
                    throw new SocialLoginFailed(SocialLoginFailed::UNVERIFIED);
                }
                if ($user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
            } else {
                $user = $this->accounts->create(
                    self::accountName($identity),
                    $identity->email,
                    emailVerified: $identity->emailVerified,
                );
                $created = true;
            }

            SocialAccount::query()->create([
                'user_id' => $user->id,
                'provider' => $identity->provider,
                'subject' => $identity->subject,
                'email' => $identity->email,
            ]);

            return [$user, $created, $passwordRemoved];
        });
    }

    /** The provider's name for the person, else the start of a real address, else « Moi ». */
    private static function accountName(SocialIdentity $identity): string
    {
        $name = $identity->name;
        if ($name === null && $identity->email !== null && ! $identity->isRelayEmail()) {
            $name = Str::before($identity->email, '@');
        }

        return Str::limit(trim((string) $name) ?: 'Moi', 50, '');
    }

    private function callbackUrl(SocialProvider $provider): string
    {
        return route('v1.auth.social.callback', ['provider' => $provider->name()]);
    }

    /** @param  array<string, string>  $query */
    private static function back(string $redirect, array $query): string
    {
        return $redirect.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private static function stateKey(string $state): string
    {
        return 'social-login:state:'.hash('sha256', $state);
    }

    private static function codeKey(string $code): string
    {
        return 'social-login:code:'.hash('sha256', $code);
    }
}
