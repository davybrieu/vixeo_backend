<?php

use App\Models\User;
use App\Notifications\EmailLoginCode;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('does not create an account until the verified e-mail completes profile registration', function () {
    $email = 'new-member@example.com';

    $this->postJson('/api/v1/auth/email/code', ['email' => $email])
        ->assertOk()
        ->assertJsonPath('expires_in_seconds', 600);

    expect(User::query()->where('email', $email)->exists())->toBeFalse();

    $code = null;
    Notification::assertSentOnDemand(EmailLoginCode::class, function (EmailLoginCode $notification) use (&$code) {
        $code = $notification->code;
        return true;
    });

    expect($code)->toMatch('/^\\d{6}$/');

    $verification = $this->postJson('/api/v1/auth/email/verify', [
        'email' => $email,
        'code' => $code,
        'device_name' => 'Pixel 8',
    ])->assertOk()
        ->assertJsonPath('created', true)
        ->assertJsonPath('registration_required', true)
        ->assertJsonStructure(['registration_ticket']);

    expect(User::query()->where('email', $email)->exists())->toBeFalse();

    $this->postJson('/api/v1/auth/register/profile', [
        'registration_ticket' => $verification->json('registration_ticket'),
        'device_name' => 'Pixel 8',
        'name' => 'Camille Martin',
        'username' => 'camille_martin',
        'bio' => 'Cinéma et séries',
        'avatar' => app(AvatarCatalog::class)->ids()[0],
        'cover' => app(CoverCatalog::class)->ids()[0],
        'social_links' => [],
    ])->assertCreated()
        ->assertJsonPath('created', true)
        ->assertJsonPath('needs_profile_setup', false)
        ->assertJsonStructure(['token', 'user' => ['id', 'email', 'email_verified']]);

    $user = User::query()->where('email', $email)->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->profiles)->toHaveCount(1)
        ->and($user->profiles->first()->name)->toBe('Camille Martin')
        ->and($user->profiles->first()->username)->toBe('camille_martin')
        ->and($user->profiles->first()->bio)->toBe('Cinéma et séries')
        ->and($user->profiles->first()->registration_completed_at)->not->toBeNull();
});

it('limits wrong OTP attempts to five per email and IP in ten minutes', function () {
    $email = 'brute-force@example.com';

    $this->postJson('/api/v1/auth/email/code', ['email' => $email])->assertOk();
    $wrongCode = '000000';
    $issuedCode = null;
    Notification::assertSentOnDemand(EmailLoginCode::class, function (EmailLoginCode $notification) use (&$issuedCode) {
        $issuedCode = $notification->code;
        return true;
    });
    if ($wrongCode === $issuedCode) $wrongCode = '000001';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/auth/email/verify', [
            'email' => $email,
            'code' => $wrongCode,
            'device_name' => 'Pixel',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    $this->postJson('/api/v1/auth/email/verify', [
        'email' => $email,
        'code' => '000000',
        'device_name' => 'Pixel',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('expires email OTP codes after ten minutes', function () {
    $email = 'expires@example.com';

    $this->postJson('/api/v1/auth/email/code', ['email' => $email])->assertOk();

    $code = null;
    Notification::assertSentOnDemand(EmailLoginCode::class, function (EmailLoginCode $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    $this->travel(11)->minutes();

    $this->postJson('/api/v1/auth/email/verify', [
        'email' => $email,
        'code' => $code,
        'device_name' => 'Pixel',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');

    expect(User::query()->where('email', $email)->exists())->toBeFalse();
});
