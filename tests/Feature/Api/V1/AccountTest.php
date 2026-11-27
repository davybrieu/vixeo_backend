<?php

use App\Models\DeviceLogin;
use App\Models\ProfileItem;
use App\Models\User;
use App\Notifications\AccountDataExport;
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

it('changes the email address without a password', function () {
    $this->withToken($this->token)->postJson('/api/v1/me/email', ['email' => 'nouveau@example.com'])
        ->assertOk()
        ->assertJsonPath('data.email', 'nouveau@example.com')
        ->assertJsonPath('data.email_verified', false);

    expect($this->user->fresh()->email)->toBe('nouveau@example.com');
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
        ->assertJsonPath('data.0.name', 'Salon TV')
        ->assertJsonStructure(['data' => [['id', 'name', 'current', 'signed_in_at', 'last_used_at', 'expires_at']]]);

    $this->withToken($this->token)->deleteJson('/api/v1/me/devices/'.$tv->accessToken->id)->assertNoContent();
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tv->accessToken->id]);
});
