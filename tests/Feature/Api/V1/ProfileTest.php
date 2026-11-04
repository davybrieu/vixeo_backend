<?php

use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('lists only the account profiles, ordered by position', function () {
    Profile::factory()->for($this->user)->create(['name' => 'B', 'position' => 2]);
    Profile::factory()->for($this->user)->create(['name' => 'A', 'position' => 1]);
    Profile::factory()->create(['name' => 'Someone else']);

    $this->getJson('/api/v1/profiles')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'A')
        ->assertJsonPath('data.1.name', 'B');
});

it('creates a kid profile with a PIN without exposing the PIN', function () {
    $response = $this->postJson('/api/v1/profiles', [
        'name' => 'Léa',
        'is_kid' => true,
        'max_age_rating' => 10,
        'pin' => '4321',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Léa')
        ->assertJsonPath('data.is_kid', true)
        ->assertJsonPath('data.max_age_rating', 10)
        ->assertJsonPath('data.has_pin', true)
        ->assertJsonMissingPath('data.pin');

    $profile = Profile::firstWhere('name', 'Léa');
    expect($profile->pin)->not->toBe('4321')
        ->and($profile->checkPin('4321'))->toBeTrue();
});

it('rejects duplicate profile names within an account', function () {
    Profile::factory()->for($this->user)->create(['name' => 'Davy']);
    Profile::factory()->create(['name' => 'Léa']);

    $this->postJson('/api/v1/profiles', ['name' => 'Davy'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    // Same name in another account is fine.
    $this->postJson('/api/v1/profiles', ['name' => 'Léa'])->assertCreated();
});

it('rejects invalid PINs', function (mixed $pin) {
    $this->postJson('/api/v1/profiles', ['name' => 'X', 'pin' => $pin])
        ->assertUnprocessable()->assertJsonValidationErrors('pin');
})->with(['123', '12345', 'abcd']);

it('limits the number of profiles per account', function () {
    Profile::factory()->for($this->user)->count(Profile::MAX_PER_USER)->create();

    $this->postJson('/api/v1/profiles', ['name' => 'One too many'])->assertForbidden();
});

it('updates a profile and can remove its PIN', function () {
    $profile = Profile::factory()->for($this->user)->withPin()->create();

    // Removing a PIN requires knowing it.
    $this->patchJson("/api/v1/profiles/{$profile->id}", ['pin' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('current_pin');

    $this->patchJson("/api/v1/profiles/{$profile->id}", ['name' => 'Renamed', 'pin' => null, 'current_pin' => '1234'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.has_pin', false);
});

it('keeps the name when updating other fields', function () {
    $profile = Profile::factory()->for($this->user)->create(['name' => 'Davy']);

    $this->patchJson("/api/v1/profiles/{$profile->id}", ['name' => 'Davy', 'avatar' => 'arcade-cat'])
        ->assertOk()
        ->assertJsonPath('data.avatar', 'arcade-cat');
});

it('forbids access to another account profile', function () {
    $other = Profile::factory()->create();

    $this->getJson("/api/v1/profiles/{$other->id}")->assertForbidden();
    $this->patchJson("/api/v1/profiles/{$other->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->deleteJson("/api/v1/profiles/{$other->id}")->assertForbidden();
    $this->postJson("/api/v1/profiles/{$other->id}/verify-pin", ['pin' => '1234'])->assertForbidden();
});

it('deletes a profile but never the last one', function () {
    [$first, $second] = Profile::factory()->for($this->user)->count(2)->create();

    $this->deleteJson("/api/v1/profiles/{$first->id}")->assertNoContent();
    $this->deleteJson("/api/v1/profiles/{$second->id}")->assertForbidden();

    expect($this->user->profiles()->count())->toBe(1);
});

it('verifies a profile PIN', function () {
    $profile = Profile::factory()->for($this->user)->withPin('1234')->create();

    $this->postJson("/api/v1/profiles/{$profile->id}/verify-pin", ['pin' => '1234'])->assertNoContent();
    $this->postJson("/api/v1/profiles/{$profile->id}/verify-pin", ['pin' => '0000'])
        ->assertUnprocessable()->assertJsonValidationErrors('pin');
});

it('rejects any PIN on a profile without PIN', function () {
    $profile = Profile::factory()->for($this->user)->create();

    $this->postJson("/api/v1/profiles/{$profile->id}/verify-pin", ['pin' => '1234'])->assertUnprocessable();
});

it('throttles PIN attempts', function () {
    $profile = Profile::factory()->for($this->user)->withPin('1234')->create();

    foreach (range(1, 5) as $i) {
        $this->postJson("/api/v1/profiles/{$profile->id}/verify-pin", ['pin' => '0000'])->assertUnprocessable();
    }

    $this->postJson("/api/v1/profiles/{$profile->id}/verify-pin", ['pin' => '1234'])->assertTooManyRequests();
});
