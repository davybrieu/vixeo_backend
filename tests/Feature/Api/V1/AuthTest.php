<?php

use App\Models\User;
use App\Notifications\EmailLoginLink;
use App\Services\SocialLogin\SocialLogins;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function emailLoginPayload(string $email = 'davy@example.com', string $verifier = 'a123456789012345678901234567890123456789012'): array
{
    return [
        'email' => $email,
        'redirect_uri' => SocialLogins::APP_REDIRECT,
        'code_challenge' => SocialLogins::challenge($verifier),
    ];
}

function consumeEmailLoginLink(User $user, string $verifier = 'a123456789012345678901234567890123456789012'): string
{
    $url = null;
    Notification::assertSentTo($user, EmailLoginLink::class, function (EmailLoginLink $notification) use (&$url) {
        $url = $notification->url;
        return true;
    });

    $path = (string) parse_url((string) $url, PHP_URL_PATH);
    $query = (string) parse_url((string) $url, PHP_URL_QUERY);
    $response = test()->get($path.'?'.$query);
    expect($response->status())->toBe(303);
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $callback);
    return (string) ($callback['code'] ?? '');
}

beforeEach(fn () => Notification::fake());

it('registers a passwordless account and sends a one-time email link', function () {
    $payload = emailLoginPayload();
    $payload += ['name' => 'Davy', 'username' => 'davy'];

    $this->postJson('/api/v1/auth/register', $payload)
        ->assertCreated()
        ->assertJsonPath('message', 'Votre compte a été créé. Consultez votre boîte e-mail pour vous connecter.');

    $user = User::where('email', 'davy@example.com')->firstOrFail();
    expect($user->profiles)->toHaveCount(1)
        ->and($user->profiles->first()->name)->toBe('Davy')
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, EmailLoginLink::class);
});

it('validates registration input without asking for a password', function () {
    $payload = emailLoginPayload('taken@example.com') + ['name' => '', 'username' => 'x'];
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email']);
});

it('sends an email link to an existing account and signs in after it is consumed', function () {
    $user = User::factory()->unverified()->create(['email' => 'davy@example.com']);
    $verifier = 'a123456789012345678901234567890123456789012';
    $this->postJson('/api/v1/auth/email/link', emailLoginPayload(verifier: $verifier))
        ->assertOk()
        ->assertJsonPath('expires_in_minutes', 15);

    $code = consumeEmailLoginLink($user);
    expect($code)->not->toBeEmpty();

    $response = $this->postJson('/api/v1/auth/email/token', [
        'code' => $code,
        'code_verifier' => $verifier,
        'device_name' => 'Pixel 8',
    ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email', 'email_verified']])
      ->assertJsonPath('user.email_verified', true);

    expect($user->fresh()->email_verified_at)->not->toBeNull()
        ->and($user->tokens()->first()->name)->toBe('Pixel 8');
});

it('does not reveal whether an email address has an account', function () {
    $payload = emailLoginPayload('nobody@example.com');
    $this->postJson('/api/v1/auth/email/link', $payload)
        ->assertOk()
        ->assertJsonPath('message', 'Si un compte utilise cette adresse, un lien de connexion vient de lui être envoyé.');
    Notification::assertNothingSent();
});

it('allows a magic link to be consumed only once', function () {
    $user = User::factory()->create(['email' => 'davy@example.com']);
    $this->postJson('/api/v1/auth/email/link', emailLoginPayload())->assertOk();
    $code = consumeEmailLoginLink($user);
    $this->postJson('/api/v1/auth/email/token', [
        'code' => $code,
        'code_verifier' => 'a123456789012345678901234567890123456789012',
        'device_name' => 'Pixel',
    ])->assertOk();
    $this->postJson('/api/v1/auth/email/token', [
        'code' => $code,
        'code_verifier' => 'a123456789012345678901234567890123456789012',
        'device_name' => 'Pixel',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('rejects a wrong PKCE verifier', function () {
    $user = User::factory()->create(['email' => 'davy@example.com']);
    $this->postJson('/api/v1/auth/email/link', emailLoginPayload())->assertOk();
    $code = consumeEmailLoginLink($user);
    $this->postJson('/api/v1/auth/email/token', [
        'code' => $code,
        'code_verifier' => str_repeat('z', 43),
        'device_name' => 'Pixel',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('returns the authenticated user and protects personal endpoints', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
    $this->getJson('/api/v1/profiles')->assertOk();
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
    expect($user->tokens()->count())->toBe(0);
});
