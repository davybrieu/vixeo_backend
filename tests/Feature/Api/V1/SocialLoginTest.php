<?php

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialLogin\SocialLogins;
use App\Services\AvatarCatalog;
use App\Services\CoverCatalog;
use App\Support\Jwt;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'services.google.client_id' => 'google-client.apps.googleusercontent.com',
        'services.google.client_secret' => 'google-secret',
    ]);
});

/** An ID token as the provider's token endpoint gives it (its signature isn't read). */
function idToken(array $claims): string
{
    return Jwt::base64Url('{"alg":"RS256"}').'.'.Jwt::base64Url((string) json_encode($claims)).'.c2lnbmF0dXJl';
}

function googleAnswers(array $claims): void
{
    // A new answer replaces the one before (the fakes of Http add up, the first one wins).
    Http::swap(new HttpFactory(app('events')));
    Http::preventStrayRequests();
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['id_token' => idToken($claims + [
        'iss' => 'https://accounts.google.com',
        'aud' => 'google-client.apps.googleusercontent.com',
        'exp' => time() + 3600,
    ])])]);
}

/** A PKCE pair: [verifier, challenge]. */
function pkce(): array
{
    $verifier = str_repeat('v', 20).bin2hex(random_bytes(16));

    return [$verifier, SocialLogins::challenge($verifier)];
}

/** Starts a sign-in like the app: the state sent to the provider. */
function startSignIn($test, string $provider, string $challenge, string $redirect = SocialLogins::APP_REDIRECT): string
{
    $location = $test->get("/api/v1/auth/social/{$provider}?".http_build_query([
        'redirect_uri' => $redirect,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return $query['state'];
}

/** The query of the address the browser is sent back to. */
function backQuery($response): array
{
    $location = (string) $response->headers->get('Location');
    expect($location)->toStartWith(SocialLogins::APP_REDIRECT.'?');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return $query;
}

it('lists the providers and whether the server has their settings', function () {
    $this->getJson('/api/v1/auth/social')->assertOk()->assertExactJson(['data' => [
        ['provider' => 'google', 'name' => 'Google', 'available' => true],
        ['provider' => 'apple', 'name' => 'Apple', 'available' => false],
    ]]);
});

it('sends the browser to Google with its own state, and only back to the app', function () {
    [, $challenge] = pkce();

    $location = $this->get('/api/v1/auth/social/google?'.http_build_query([
        'redirect_uri' => SocialLogins::APP_REDIRECT, 'code_challenge' => $challenge,
    ]))->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
        ->toContain('client_id=google-client.apps.googleusercontent.com')
        ->toContain('scope=openid%20email%20profile')
        ->toContain('redirect_uri='.rawurlencode('https://api.vixeo.test/api/v1/auth/social/google/callback'))
        ->not->toContain($challenge);

    // Never a website, never another port of another host.
    foreach (['https://evil.example/auth', 'http://localhost:5000/auth', 'http://127.0.0.1:80/auth', 'http://127.0.0.1:5000/x', 'app.vixeo://other'] as $redirect) {
        $this->getJson('/api/v1/auth/social/google?'.http_build_query(['redirect_uri' => $redirect, 'code_challenge' => $challenge]))
            ->assertUnprocessable()->assertJsonValidationErrors('redirect_uri');
    }
    $this->getJson('/api/v1/auth/social/google?'.http_build_query(['redirect_uri' => SocialLogins::APP_REDIRECT, 'code_challenge' => 'short']))
        ->assertJsonValidationErrors('code_challenge');
    $this->getJson('/api/v1/auth/social/facebook?'.http_build_query(['redirect_uri' => SocialLogins::APP_REDIRECT, 'code_challenge' => $challenge]))
        ->assertNotFound();
    // Windows: a server of the app on the device.
    $this->get('/api/v1/auth/social/google?'.http_build_query(['redirect_uri' => 'http://127.0.0.1:53123/auth', 'code_challenge' => $challenge]))
        ->assertRedirect();
});

it('sends the app back an error when the server has no settings for the provider', function () {
    [, $challenge] = pkce();

    $this->get('/api/v1/auth/social/apple?'.http_build_query(['redirect_uri' => SocialLogins::APP_REDIRECT, 'code_challenge' => $challenge]))
        ->assertRedirect(SocialLogins::APP_REDIRECT.'?error=unavailable');
});

it('keeps a new Google identity pending until the profile is completed', function () {
    [$verifier, $challenge] = pkce();
    $state = startSignIn($this, 'google', $challenge);
    googleAnswers(['sub' => '1098', 'email' => 'lea@gmail.com', 'email_verified' => true, 'name' => 'Léa Martin']);

    $query = backQuery($this->get("/api/v1/auth/social/google/callback?state={$state}&code=google-code")->assertStatus(303));
    $response = $this->postJson('/api/v1/auth/social/token', [
        'code' => $query['code'], 'code_verifier' => $verifier, 'device_name' => 'Pixel 8',
    ])->assertOk()
        ->assertJsonPath('created', true)
        ->assertJsonPath('registration_required', true)
        ->assertJsonStructure(['registration_ticket']);

    expect(User::where('email', 'lea@gmail.com')->exists())->toBeFalse();

    $registration = $this->postJson('/api/v1/auth/register/profile', [
        'registration_ticket' => $response->json('registration_ticket'),
        'device_name' => 'Pixel 8',
        'name' => 'Léa Martin',
        'username' => 'lea_martin',
        'bio' => 'Cinéma',
        'avatar' => app(AvatarCatalog::class)->ids()[0],
        'cover' => app(CoverCatalog::class)->ids()[0],
        'social_links' => [],
    ])->assertCreated()
        ->assertJsonPath('created', true)
        ->assertJsonPath('user.email', 'lea@gmail.com')
        ->assertJsonPath('user.email_verified', true)
        ->assertJsonPath('user.providers', ['google']);

    $user = User::where('email', 'lea@gmail.com')->firstOrFail();
    expect($user->name)->toBe('Léa Martin')
        ->and($user->profiles)->toHaveCount(1)
        ->and($user->profiles->first()->registration_completed_at)->not->toBeNull()
        ->and($user->profiles->first()->username)->toBe('lea_martin')
        ->and($user->tokens()->first()->name)->toBe('Pixel 8');
    $this->withToken($registration->json('token'))->getJson('/api/v1/me')->assertOk();

    $this->postJson('/api/v1/auth/social/token', [
        'code' => $query['code'], 'code_verifier' => $verifier, 'device_name' => 'Pixel 8',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');

    $state = startSignIn($this, 'google', $challenge);
    googleAnswers(['sub' => '1098', 'email' => 'lea.martin@gmail.com', 'email_verified' => true]);
    $query = backQuery($this->get("/api/v1/auth/social/google/callback?state={$state}&code=again"));
    $this->postJson('/api/v1/auth/social/token', ['code' => $query['code'], 'code_verifier' => $verifier, 'device_name' => 'PC'])
        ->assertOk()->assertJsonPath('created', false)->assertJsonPath('user.id', $user->id);
    expect(User::count())->toBe(1)
        ->and(SocialAccount::sole()->email)->toBe('lea.martin@gmail.com');
});

it('gives no token without the secret that started the sign-in', function () {
    [, $challenge] = pkce();
    [$otherVerifier] = pkce();
    $state = startSignIn($this, 'google', $challenge);
    googleAnswers(['sub' => '1', 'email' => 'lea@gmail.com', 'email_verified' => true]);
    $query = backQuery($this->get("/api/v1/auth/social/google/callback?state={$state}&code=c"));

    $this->postJson('/api/v1/auth/social/token', ['code' => $query['code'], 'code_verifier' => $otherVerifier, 'device_name' => 'X'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Cette connexion a expiré. Recommencez.');
});

it('links Google to a verified account with the same address', function () {
    $user = User::factory()->create(['email' => 'davy@gmail.com']);
    $user->createToken('TV');
    [$verifier, $challenge] = pkce();
    $state = startSignIn($this, 'google', $challenge);
    googleAnswers(['sub' => '77', 'email' => 'Davy@Gmail.com', 'email_verified' => true]);

    $query = backQuery($this->get("/api/v1/auth/social/google/callback?state={$state}&code=c"));
    $this->postJson('/api/v1/auth/social/token', ['code' => $query['code'], 'code_verifier' => $verifier, 'device_name' => 'X'])
        ->assertOk()->assertJsonPath('created', false)->assertJsonPath('user.id', $user->id);

    expect($user->fresh()->tokens()->where('name', 'TV')->exists())->toBeTrue()
        ->and(SocialAccount::where('user_id', $user->id)->sole()->subject)->toBe('77');
});

it('revokes sessions when a verified provider proves a previously unverified address', function () {
    $user = User::factory()->unverified()->create(['email' => 'lea@gmail.com']);
    $user->createToken('Old phone');
    [$verifier, $challenge] = pkce();
    $state = startSignIn($this, 'google', $challenge);
    googleAnswers(['sub' => '5', 'email' => 'lea@gmail.com', 'email_verified' => true]);

    $query = backQuery($this->get("/api/v1/auth/social/google/callback?state={$state}&code=c"));
    $this->postJson('/api/v1/auth/social/token', ['code' => $query['code'], 'code_verifier' => $verifier, 'device_name' => 'X'])
        ->assertOk()->assertJsonPath('user.email_verified', true);

    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and($user->tokens()->where('name', 'Old phone')->exists())->toBeFalse();
});

it('refuses an address Google did not check, when it belongs to an account', function () {
    User::factory()->create(['email' => 'lea@example.com']);
    [, $challenge] = pkce();
    $state = startSignIn($this, 'google', $challenge);
    googleAnswers(['sub' => '5', 'email' => 'lea@example.com', 'email_verified' => false]);

    expect(backQuery($this->get("/api/v1/auth/social/google/callback?state={$state}&code=c")))
        ->toBe(['error' => 'unverified']);
    expect(SocialAccount::count())->toBe(0);
});

it('sends the app back « cancelled », « failed », and a page for a sign-in that has expired', function () {
    [, $challenge] = pkce();

    $state = startSignIn($this, 'google', $challenge);
    expect(backQuery($this->get("/api/v1/auth/social/google/callback?state={$state}&error=access_denied")))
        ->toBe(['error' => 'cancelled']);

    $state = startSignIn($this, 'google', $challenge);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);
    expect(backQuery($this->get("/api/v1/auth/social/google/callback?state={$state}&code=c")))
        ->toBe(['error' => 'failed']);

    // The state is used: a second time is a page, not the app.
    $this->get("/api/v1/auth/social/google/callback?state={$state}&code=c")
        ->assertStatus(410)
        ->assertSee('Cette connexion a expiré');
    $this->get('/api/v1/auth/social/google/callback?state=unknown&code=c')->assertStatus(410);
    // A state of Google is not one of Apple.
    $state = startSignIn($this, 'google', $challenge);
    $this->post('/api/v1/auth/social/apple/callback', ['state' => $state, 'code' => 'c'])->assertStatus(410);
});

it('signs in with Apple: a form posted back, a client secret signed with the key, the name of the first time', function () {
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    openssl_pkey_export($key, $pem);
    config([
        'services.apple.client_id' => 'app.vixeo.signin',
        'services.apple.team_id' => 'TEAM123456',
        'services.apple.key_id' => 'KEY1234567',
        'services.apple.private_key' => 'base64:'.base64_encode($pem),
    ]);
    $this->getJson('/api/v1/auth/social')->assertJsonPath('data.1.available', true);

    [$verifier, $challenge] = pkce();
    $location = $this->get('/api/v1/auth/social/apple?'.http_build_query(['redirect_uri' => SocialLogins::APP_REDIRECT, 'code_challenge' => $challenge]))
        ->headers->get('Location');
    expect($location)->toStartWith('https://appleid.apple.com/auth/authorize?')
        ->toContain('response_mode=form_post')
        ->toContain('scope=name%20email');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $start);

    Http::fake(['appleid.apple.com/auth/token' => Http::response(['id_token' => idToken([
        'iss' => 'https://appleid.apple.com',
        'aud' => 'app.vixeo.signin',
        'exp' => time() + 600,
        'sub' => '001234.abcdef.1234',
        'email' => 'x7k2@privaterelay.appleid.com',
        'email_verified' => 'true',
    ])])]);

    $response = $this->post('/api/v1/auth/social/apple/callback', [
        'state' => $start['state'],
        'code' => 'apple-code',
        'user' => json_encode(['name' => ['firstName' => 'Tom', 'lastName' => 'Durand'], 'email' => 'x7k2@privaterelay.appleid.com']),
    ])->assertStatus(303);
    $query = backQuery($response);

    // The client secret: ES256, signed by the team's key, for this Services ID.
    Http::assertSent(function (HttpRequest $request) use ($key) {
        [$header, $claims, $signature] = explode('.', $request['client_secret']);
        $raw = base64_decode(strtr($signature, '-_', '+/'));
        $integer = fn (string $bytes) => ($bytes = ltrim($bytes, "\0")) !== '' && ord($bytes[0]) > 0x7F ? "\0".$bytes : ($bytes ?: "\0");
        [$r, $s] = [$integer(substr($raw, 0, 32)), $integer(substr($raw, 32))];
        $der = "\x30".chr(4 + strlen($r) + strlen($s))."\x02".chr(strlen($r)).$r."\x02".chr(strlen($s)).$s;
        $payload = json_decode(base64_decode(strtr($claims, '-_', '+/')), true);

        return strlen($raw) === 64
            && openssl_verify("{$header}.{$claims}", $der, openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256) === 1
            && json_decode(base64_decode(strtr($header, '-_', '+/')), true) === ['alg' => 'ES256', 'kid' => 'KEY1234567', 'typ' => 'JWT']
            && $payload['iss'] === 'TEAM123456' && $payload['sub'] === 'app.vixeo.signin' && $payload['aud'] === 'https://appleid.apple.com'
            && $request['code'] === 'apple-code';
    });

    $response = $this->postJson('/api/v1/auth/social/token', ['code' => $query['code'], 'code_verifier' => $verifier, 'device_name' => 'Pixel 8'])
        ->assertOk()
        ->assertJsonPath('created', true)
        ->assertJsonPath('registration_required', true);

    expect(User::where('email', 'x7k2@privaterelay.appleid.com')->exists())->toBeFalse();

    $this->postJson('/api/v1/auth/register/profile', [
        'registration_ticket' => $response->json('registration_ticket'),
        'device_name' => 'Pixel 8',
        'name' => 'Tom Durand',
        'username' => 'tom_durand',
        'avatar' => app(AvatarCatalog::class)->ids()[0],
        'cover' => app(CoverCatalog::class)->ids()[0],
        'social_links' => [],
    ])->assertCreated()->assertJsonPath('user.name', 'Tom Durand')
        ->assertJsonPath('user.providers', ['apple']);
});

it('names an account of an Apple relay address without its name « Moi »', function () {
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    openssl_pkey_export($key, $pem);
    config([
        'services.apple.client_id' => 'app.vixeo.signin', 'services.apple.team_id' => 'T',
        'services.apple.key_id' => 'K', 'services.apple.private_key' => $pem,
    ]);
    [$verifier, $challenge] = pkce();
    $state = startSignIn($this, 'apple', $challenge);
    Http::fake(['appleid.apple.com/auth/token' => Http::response(['id_token' => idToken([
        'iss' => 'https://appleid.apple.com', 'aud' => 'app.vixeo.signin', 'exp' => time() + 600,
        'sub' => 'apple-sub', 'email' => 'q9@privaterelay.appleid.com', 'email_verified' => true,
    ])])]);

    $query = backQuery($this->post('/api/v1/auth/social/apple/callback', ['state' => $state, 'code' => 'c']));
    $this->postJson('/api/v1/auth/social/token', ['code' => $query['code'], 'code_verifier' => $verifier, 'device_name' => 'X'])
        ->assertJsonPath('user.name', 'Moi');
});

