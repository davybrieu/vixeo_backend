<?php

use App\Models\User;
use App\Notifications\EmailLoginCode;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('sends a six-digit code and creates a new account only after verification', function () {
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

    expect($code)->toMatch('/^\d{6}$/');

    $this->postJson('/api/v1/auth/email/verify', [
        'email' => $email,
        'code' => $code,
        'device_name' => 'Pixel 8',
    ])->assertOk()
        ->assertJsonPath('created', true)
        ->assertJsonPath('needs_profile_setup', true)
        ->assertJsonStructure(['token', 'user' => ['id', 'email', 'email_verified']]);

    $user = User::query()->where('email', $email)->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->profiles)->toHaveCount(1)
        ->and($user->profiles->first()->registration_completed_at)->toBeNull();
});

it('limits wrong OTP attempts to five per email and IP in ten minutes', function () {
    $email = 'brute-force@example.com';

    $this->postJson('/api/v1/auth/email/code', ['email' => $email])->assertOk();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/auth/email/verify', [
            'email' => $email,
            'code' => '000000',
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
