<?php

use App\Models\DeviceLogin;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

function startTv(string $name = 'Vixeo TV')
{
    return test()->postJson('/api/v1/auth/device', ['device_name' => $name]);
}

it('signs a TV in once a signed-in phone approves its code', function () {
    config(['app.web_url' => 'https://vixeo.test']);

    $start = startTv()->assertOk();
    $userCode = $start->json('user_code');
    $deviceCode = $start->json('device_code');

    expect($userCode)->toMatch('/^[A-HJ-NP-Z2-9]{3}-[A-HJ-NP-Z2-9]{3}$/')
        ->and($start->json('verification_url'))->toBe('https://vixeo.test/tv')
        ->and($start->json('verification_url_complete'))->toBe('https://vixeo.test/tv?code='.str_replace('-', '', $userCode))
        ->and($start->json('expires_in'))->toBe(600)
        ->and($start->json('interval'))->toBe(5);

    // Not approved yet.
    $this->postJson('/api/v1/auth/device/token', ['device_code' => $deviceCode])
        ->assertStatus(202)->assertExactJson(['status' => 'pending']);

    // The phone looks the code up (any case, with or without dash), then approves it.
    $user = User::factory()->create(['name' => 'Davy']);
    Sanctum::actingAs($user);
    $this->postJson('/api/v1/auth/device/lookup', ['user_code' => strtolower(str_replace('-', '', $userCode))])
        ->assertOk()
        ->assertJsonPath('device_name', 'Vixeo TV')
        ->assertJsonPath('user_code', $userCode);
    $this->postJson('/api/v1/auth/device/approve', ['user_code' => $userCode])->assertNoContent();

    // The TV gets its token, once.
    app('auth')->forgetGuards();
    $token = $this->postJson('/api/v1/auth/device/token', ['device_code' => $deviceCode])
        ->assertOk()
        ->assertJsonPath('status', 'approved')
        ->assertJsonPath('user.name', 'Davy')
        ->json('token');

    expect($user->tokens()->pluck('name')->all())->toBe(['Vixeo TV']);
    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);

    app('auth')->forgetGuards();
    $this->withToken('')->postJson('/api/v1/auth/device/token', ['device_code' => $deviceCode])
        ->assertStatus(410)
        ->assertJsonPath('status', 'expired');
});

it('stores only a hash of the TV secret', function () {
    $deviceCode = startTv()->json('device_code');

    $login = DeviceLogin::sole();
    expect($login->device_code_hash)->toBe(hash('sha256', $deviceCode))
        ->and(DeviceLogin::where('device_code_hash', $deviceCode)->exists())->toBeFalse();
});

it('expires codes after 10 minutes', function () {
    $start = startTv();
    Sanctum::actingAs(User::factory()->create());

    Carbon::setTestNow(now()->addMinutes(11));

    $this->postJson('/api/v1/auth/device/approve', ['user_code' => $start->json('user_code')])
        ->assertUnprocessable()
        ->assertJsonPath('errors.user_code.0', 'Ce code est invalide ou a expiré. Affichez un nouveau code sur la TV.');
    $this->postJson('/api/v1/auth/device/token', ['device_code' => $start->json('device_code')])->assertStatus(410);
});

it('rejects unknown codes and codes already approved', function () {
    $start = startTv();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/auth/device/lookup', ['user_code' => 'AAA-AAA'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/device/approve', ['user_code' => $start->json('user_code')])->assertNoContent();
    // Someone else cannot take over an approved code.
    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/auth/device/approve', ['user_code' => $start->json('user_code')])->assertUnprocessable();
});

it('requires a signed-in account to look up or approve a code', function () {
    $start = startTv();

    $this->postJson('/api/v1/auth/device/lookup', ['user_code' => $start->json('user_code')])->assertUnauthorized();
    $this->postJson('/api/v1/auth/device/approve', ['user_code' => $start->json('user_code')])->assertUnauthorized();
});

it('limits code guesses', function () {
    Sanctum::actingAs(User::factory()->create());

    foreach (range(1, 10) as $i) {
        $this->postJson('/api/v1/auth/device/approve', ['user_code' => 'AAA-AA'.$i])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/device/approve', ['user_code' => 'AAA-AAB'])->assertTooManyRequests();
});

it('answers an unknown TV secret as expired', function () {
    $this->postJson('/api/v1/auth/device/token', ['device_code' => 'nope'])->assertStatus(410);
});

it('validates the device name', function () {
    $this->postJson('/api/v1/auth/device', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.device_name.0', "Le champ nom de l'appareil est obligatoire.");
});

it('prunes old codes', function () {
    startTv();
    Carbon::setTestNow(now()->addDays(2));

    $this->artisan('model:prune', ['--model' => [DeviceLogin::class]])->assertSuccessful();

    expect(DeviceLogin::count())->toBe(0);
});
