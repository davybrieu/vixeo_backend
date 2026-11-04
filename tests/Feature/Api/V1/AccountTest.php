<?php

use App\Models\DeviceLogin;
use App\Models\ProfileItem;
use App\Models\User;
use App\Notifications\AccountDataExport;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Davy', 'email' => 'davy@example.com', 'password' => 'secret-password']);
    $this->token = $this->user->createToken('iPhone')->plainTextToken;
});

it('deletes the account and everything in it with the password', function () {
    $profile = $this->user->profiles()->create(['name' => 'Davy']);
    ProfileItem::factory()->for($profile)->create(['title' => 'Nord Sauvage']);
    $this->user->createToken('Salon TV');

    $this->withToken($this->token)->postJson('/api/v1/me/delete', ['password' => 'secret-password'])
        ->assertNoContent();

    expect(User::count())->toBe(0);
    $this->assertDatabaseCount('profiles', 0);
    $this->assertDatabaseCount('profile_items', 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
    // The old token no longer works.
    app('auth')->forgetGuards();
    $this->withToken($this->token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('keeps the account with a wrong password', function () {
    $this->withToken($this->token)->postJson('/api/v1/me/delete', ['password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', 'Mot de passe incorrect.');

    expect(User::count())->toBe(1);
});

it('limits password guesses when deleting', function () {
    foreach (range(1, 5) as $i) {
        $this->withToken($this->token)->postJson('/api/v1/me/delete', ['password' => 'wrong'])->assertUnprocessable();
    }

    $this->withToken($this->token)->postJson('/api/v1/me/delete', ['password' => 'secret-password'])->assertTooManyRequests();
});

it('removes pending TV sign-ins with the account', function () {
    DeviceLogin::create([
        'device_code_hash' => hash('sha256', 'x'), 'user_code' => 'ABC234', 'device_name' => 'TV',
        'user_id' => $this->user->id, 'approved_at' => now(), 'expires_at' => now()->addMinutes(5),
    ]);

    $this->withToken($this->token)->postJson('/api/v1/me/delete', ['password' => 'secret-password'])->assertNoContent();

    $this->assertDatabaseCount('device_logins', 0);
});

it('e-mails the account data as JSON', function () {
    Notification::fake();
    $profile = $this->user->profiles()->create(['name' => 'Léa', 'pin' => '1234']);
    ProfileItem::factory()->for($profile)->create(['title' => 'Nord Sauvage']);

    $this->withToken($this->token)->postJson('/api/v1/me/export')
        ->assertAccepted()
        ->assertJsonPath('message', 'Vos données ont été envoyées à davy@example.com.');

    Notification::assertSentTo($this->user, AccountDataExport::class, function (AccountDataExport $n) {
        $data = $n->data;
        $mail = $n->toMail($this->user);
        $json = json_encode($data);

        return $data['account']['email'] === 'davy@example.com'
            && $data['profiles'][0]['name'] === 'Léa'
            && $data['profiles'][0]['has_pin'] === true
            && $data['profiles'][0]['library'][0]['title'] === 'Nord Sauvage'
            && $data['devices'][0]['name'] === 'iPhone'
            && ! str_contains($json, '$2y$') // no hash
            && $mail->subject === 'Vos données Vixeo'
            && $mail->rawAttachments[0]['name'] === 'vixeo-donnees.json';
    });
});

it('limits data exports to 3 per hour', function () {
    Notification::fake();
    foreach (range(1, 3) as $i) {
        $this->withToken($this->token)->postJson('/api/v1/me/export')->assertAccepted();
    }

    $this->withToken($this->token)->postJson('/api/v1/me/export')->assertTooManyRequests();
});

it('lists the signed-in devices and marks the current one', function () {
    $tv = $this->user->createToken('Salon TV');
    $tv->accessToken->forceFill(['last_used_at' => now()->subDay()])->save();
    User::factory()->create()->createToken('Someone else');

    $this->withToken($this->token)->getJson('/api/v1/me/devices')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'iPhone')
        ->assertJsonPath('data.0.current', true)
        ->assertJsonPath('data.1.name', 'Salon TV')
        ->assertJsonPath('data.1.current', false)
        ->assertJsonStructure(['data' => [['id', 'name', 'current', 'signed_in_at', 'last_used_at', 'expires_at']]]);
});

it('signs out another device', function () {
    $tv = $this->user->createToken('Salon TV');

    $this->withToken($this->token)->deleteJson('/api/v1/me/devices/'.$tv->accessToken->id)->assertNoContent();

    app('auth')->forgetGuards();
    $this->withToken($tv->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
});

it("can't sign out someone else's device", function () {
    $other = User::factory()->create()->createToken('Their TV');

    $this->withToken($this->token)->deleteJson('/api/v1/me/devices/'.$other->accessToken->id)->assertNotFound();

    $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
});

it('expires tokens after 180 days', function () {
    $this->travel(179)->days();
    $this->withToken($this->token)->getJson('/api/v1/me')->assertOk();

    $this->travel(2)->days();
    app('auth')->forgetGuards();
    $this->withToken($this->token)->getJson('/api/v1/me')->assertUnauthorized();
});
