<?php

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Facades\Notification;

function requestResetCode(string $email = 'davy@example.com'): ?string
{
    test()->postJson('/api/v1/auth/password/forgot', ['email' => $email])
        ->assertOk()
        ->assertJsonPath('message', 'Si un compte utilise cette adresse, un code vient d’y être envoyé.')
        ->assertJsonPath('expires_in_minutes', 15);

    $code = null;
    Notification::assertSentTo(User::where('email', $email)->first() ?? new User, PasswordResetCode::class,
        function (PasswordResetCode $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

    return $code;
}

function resetWith(string $code, string $password = 'new-secret-password', string $email = 'davy@example.com')
{
    return test()->postJson('/api/v1/auth/password/reset', [
        'email' => $email,
        'code' => $code,
        'password' => $password,
        'device_name' => 'iPhone',
    ]);
}

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->create(['name' => 'Davy', 'email' => 'davy@example.com', 'password' => 'old-password']);
});

it('e-mails a 6-digit code in French', function () {
    $code = requestResetCode();

    expect($code)->toMatch('/^\d{6}$/');
    Notification::assertSentTo($this->user, PasswordResetCode::class, function (PasswordResetCode $n) {
        $mail = $n->toMail($this->user);

        return $mail->subject === 'Votre code pour réinitialiser votre mot de passe Vixeo'
            && in_array('**'.$n->code.'**', $mail->introLines, true)
            && $mail->greeting === 'Bonjour Davy,';
    });
    // Only a hash is stored.
    $this->assertDatabaseMissing('password_reset_tokens', ['token' => $code]);
});

it('answers the same for an unknown address and sends nothing', function () {
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'Si un compte utilise cette adresse, un code vient d’y être envoyé.');

    Notification::assertNothingSent();
});

it('sets the new password, signs out every device and signs this one in', function () {
    $this->user->createToken('Salon TV');
    $code = requestResetCode();

    resetWith($code)
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'email']]);

    $this->user->refresh();
    expect(Hash::check('new-secret-password', $this->user->password))->toBeTrue()
        ->and($this->user->tokens()->pluck('name')->all())->toBe(['iPhone']);
    $this->postJson('/api/v1/auth/login', ['email' => 'davy@example.com', 'password' => 'new-secret-password', 'device_name' => 'x'])
        ->assertOk();
});

it('works only once', function () {
    $code = requestResetCode();
    resetWith($code)->assertOk();

    resetWith($code, 'another-password')
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Ce code est incorrect ou a expiré. Demandez un nouveau code si besoin.');
});

it('refuses a wrong code', function () {
    requestResetCode();

    resetWith('000000')->assertUnprocessable()->assertJsonValidationErrors('code');
    expect(Hash::check('old-password', $this->user->fresh()->password))->toBeTrue();
});

it('refuses an expired code', function () {
    $code = requestResetCode();
    $this->travel(16)->minutes();

    resetWith($code)->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('invalidates the code after 5 wrong guesses', function () {
    $code = requestResetCode();
    $wrong = $code === '111111' ? '222222' : '111111';
    foreach (range(1, 5) as $i) {
        resetWith($wrong)->assertUnprocessable();
    }

    resetWith($code)
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Trop de codes incorrects. Demandez un nouveau code.');
    // A new code works again.
    resetWith(requestResetCode())->assertOk();
});

it('limits the codes sent to one address', function () {
    foreach (range(1, 4) as $i) {
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'davy@example.com'])->assertOk();
    }

    Notification::assertSentToTimes($this->user, PasswordResetCode::class, 3);
});

it('validates the new password and the code format', function () {
    $this->postJson('/api/v1/auth/password/reset', ['email' => 'davy@example.com', 'code' => '12', 'password' => 'short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'password', 'device_name']);
});
