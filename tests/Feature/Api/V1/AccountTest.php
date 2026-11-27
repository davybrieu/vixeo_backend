<?php

use App\Models\DeviceLogin;
use App\Models\ProfileItem;
use App\Models\User;
use App\Notifications\AccountDataExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
    $profile = $this->user->profiles()->create(['name' => 'Léa']);
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

it('changes the e-mail address with the password', function () {
    DB::table('password_reset_tokens')->insert(['email' => 'davy@example.com', 'token' => 'x', 'created_at' => now()]);

    $this->withToken($this->token)->postJson('/api/v1/me/email', ['email' => 'nouveau@example.com', 'password' => 'secret-password'])
        ->assertOk()
        ->assertJsonPath('data.email', 'nouveau@example.com');

    expect($this->user->fresh()->email)->toBe('nouveau@example.com');
    $this->assertDatabaseCount('password_reset_tokens', 0);

    // The new address signs in.
    $this->postJson('/api/v1/auth/login', ['email' => 'nouveau@example.com', 'password' => 'secret-password', 'device_name' => 'Pixel'])
        ->assertOk();
});

it('keeps the e-mail address with a wrong password, or one already used', function () {
    User::factory()->create(['email' => 'pris@example.com']);

    $this->withToken($this->token)->postJson('/api/v1/me/email', ['email' => 'nouveau@example.com', 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', 'Mot de passe incorrect.');
    $this->withToken($this->token)->postJson('/api/v1/me/email', ['email' => 'pris@example.com', 'password' => 'secret-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
    $this->withToken($this->token)->postJson('/api/v1/me/email', ['email' => 'pas-une-adresse', 'password' => 'secret-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect($this->user->fresh()->email)->toBe('davy@example.com');
});

it('changes the password, and signs out the other devices', function () {
    $tv = $this->user->createToken('Salon TV');

    $this->withToken($this->token)->postJson('/api/v1/me/password', [
        'current_password' => 'secret-password',
        'password' => 'un-nouveau-mot-de-passe',
    ])->assertNoContent();

    expect(Hash::check('un-nouveau-mot-de-passe', $this->user->fresh()->password))->toBeTrue();
    app('auth')->forgetGuards();
    // This device stays signed in, the TV is signed out.
    $this->withToken($this->token)->getJson('/api/v1/me')->assertOk();
    app('auth')->forgetGuards();
    $this->withToken($tv->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
});

it('keeps the password with a wrong current password, or a too short new one', function () {
    $this->withToken($this->token)->postJson('/api/v1/me/password', ['current_password' => 'wrong', 'password' => 'un-nouveau-mot-de-passe'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.current_password.0', 'Mot de passe incorrect.');
    $this->withToken($this->token)->postJson('/api/v1/me/password', ['current_password' => 'secret-password', 'password' => 'court'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(Hash::check('secret-password', $this->user->fresh()->password))->toBeTrue();
});
