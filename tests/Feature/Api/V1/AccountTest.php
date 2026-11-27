<?php

use App\Models\DeviceLogin;
use App\Models\ProfileItem;
use App\Models\User;
use App\Notifications\AccountDataExport;
use App\Notifications\EmailChangeLink;
use App\Services\SocialLogin\SocialLogins;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Davy', 'email' => 'davy@example.com']);
    $this->token = $this->user->createToken('iPhone')->plainTextToken;
});

it('deletes the account and all its data without a password', function () {
    $profile = $this->user->profiles()->create(['name' => 'Davy']);
    ProfileItem::factory()->for($profile)->create(['title' => 'Nord Sauvage']);
    $this->user->createToken('Salon TV');

    $this->withToken($this->token)->postJson('/api/v1/me/delete')->assertNoContent();

    expect(User::count())->toBe(0);
    $this->assertDatabaseCount('profiles', 0);
    $this->assertDatabaseCount('profile_items', 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('removes pending TV sign-ins with the account', function () {
    DeviceLogin::create([
        'device_code_hash' => hash('sha256', 'x'), 'user_code' => 'ABC234', 'device_name' => 'TV',
        'user_id' => $this->user->id, 'approved_at' => now(), 'expires_at' => now()->addMinutes(5),
    ]);
    $this->withToken($this->token)->postJson('/api/v1/me/delete')->assertNoContent();
    $this->assertDatabaseCount('device_logins', 0);
});

it('changes the email address only after its one-time confirmation link is used', function () {
    Notification::fake();
    $verifier = 'a123456789012345678901234567890123456789012';
    $this->withToken($this->token)->postJson('/api/v1/me/email', [
        'email' => 'nouveau@example.com',
        'redirect_uri' => SocialLogins::APP_REDIRECT,
        'code_challenge' => SocialLogins::challenge($verifier),
    ])->assertOk()->assertJsonPath('message', 'Un lien de confirmation a été envoyé à votre nouvelle adresse e-mail.');

    expect($this->user->fresh()->email)->toBe('davy@example.com');
    $url = null;
    Notification::assertSentOnDemand(EmailChangeLink::class, function (EmailChangeLink $mail) use (&$url) {
        $url = $mail->url;
        return true;
    });

    $path = (string) parse_url((string) $url, PHP_URL_PATH);
    $query = (string) parse_url((string) $url, PHP_URL_QUERY);
    $callback = $this->get($path.'?'.$query)->assertStatus(303)->headers->get('Location');
    parse_str((string) parse_url((string) $callback, PHP_URL_QUERY), $params);

    $this->postJson('/api/v1/auth/email/token', [
        'code' => $params['code'] ?? '',
        'code_verifier' => $verifier,
        'device_name' => 'iPhone',
    ])->assertOk()->assertJsonPath('user.email', 'nouveau@example.com')
      ->assertJsonPath('user.email_verified', true);

    expect($this->user->fresh()->email)->toBe('nouveau@example.com')
        ->and($this->user->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects an invalid or already used email address', function () {
    User::factory()->create(['email' => 'pris@example.com']);
    $this->withToken($this->token)->postJson('/api/v1/me/email', ['email' => 'pris@example.com'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->withToken($this->token)->postJson('/api/v1/me/email', ['email' => 'pas-une-adresse'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    expect($this->user->fresh()->email)->toBe('davy@example.com');
});

it('e-mails the account data as JSON without authentication secrets', function () {
    Notification::fake();
    $profile = $this->user->profiles()->create(['name' => 'Léa']);
    ProfileItem::factory()->for($profile)->create(['title' => 'Nord Sauvage']);

    $this->withToken($this->token)->postJson('/api/v1/me/export')
        ->assertAccepted()
        ->assertJsonPath('message', 'Vos données ont été envoyées à davy@example.com.');

    Notification::assertSentTo($this->user, AccountDataExport::class, function (AccountDataExport $n) {
        $data = $n->data;
        $mail = $n->toMail($this->user);
        return $data['account']['email'] === 'davy@example.com'
            && ! array_key_exists('has_password', $data['account'])
            && $data['profiles'][0]['name'] === 'Léa'
            && $data['profiles'][0]['library'][0]['title'] === 'Nord Sauvage'
            && $mail->subject === 'Vos données Vixeo'
            && $mail->rawAttachments[0]['name'] === 'vixeo-donnees.json';
    });
});

it('lists and revokes signed-in devices', function () {
    $tv = $this->user->createToken('Salon TV');
    $this->withToken($this->token)->getJson('/api/v1/me/devices')
        ->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'iPhone')
        ->assertJsonPath('data.1.name', 'Salon TV')
        ->assertJsonStructure(['data' => [['id', 'name', 'current', 'signed_in_at', 'last_used_at', 'expires_at']]]);

    $this->withToken($this->token)->deleteJson('/api/v1/me/devices/'.$tv->accessToken->id)->assertNoContent();
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tv->accessToken->id]);
});
