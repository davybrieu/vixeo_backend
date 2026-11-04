<?php

use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create(['password' => 'secret-password']);
    $this->adult = Profile::factory()->for($this->user)->withPin('1111')->create(['name' => 'Parent']);
    $this->kid = Profile::factory()->for($this->user)->kid(10)->create(['name' => 'Léa']);
    Sanctum::actingAs($this->user);
});

function enableParentalControl(string $pin = '9999'): void
{
    test()->putJson('/api/v1/parental-control/pin', ['pin' => $pin])->assertNoContent();
}

it('is off by default and can be turned on', function () {
    $this->getJson('/api/v1/parental-control')->assertExactJson(['enabled' => false]);
    $this->getJson('/api/v1/me')->assertJsonPath('data.parental_control', false);

    enableParentalControl();

    $this->getJson('/api/v1/parental-control')->assertExactJson(['enabled' => true]);
    $this->getJson('/api/v1/me')->assertJsonPath('data.parental_control', true);
    expect($this->user->fresh()->parental_pin)->not->toBe('9999');
});

it('changes the parental PIN only with the current PIN or the account password', function () {
    enableParentalControl('9999');

    $this->putJson('/api/v1/parental-control/pin', ['pin' => '1234'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.current_pin.0', 'Le code PIN parental est obligatoire.');

    $this->putJson('/api/v1/parental-control/pin', ['pin' => '1234', 'current_pin' => '0000'])
        ->assertJsonPath('errors.current_pin.0', 'Code PIN parental incorrect.');

    $this->putJson('/api/v1/parental-control/pin', ['pin' => '1234', 'current_pin' => '9999'])->assertNoContent();

    // Forgotten PIN: the account password works too.
    $this->putJson('/api/v1/parental-control/pin', ['pin' => '5678', 'password' => 'wrong'])
        ->assertJsonValidationErrors('password');
    $this->putJson('/api/v1/parental-control/pin', ['pin' => '5678', 'password' => 'secret-password'])->assertNoContent();

    $this->postJson('/api/v1/parental-control/verify', ['pin' => '5678'])->assertNoContent();
});

it('turns parental control off', function () {
    enableParentalControl('9999');

    $this->postJson('/api/v1/parental-control/disable')->assertUnprocessable();
    $this->postJson('/api/v1/parental-control/disable', ['current_pin' => '9999'])->assertNoContent();

    expect($this->user->fresh()->hasParentalControl())->toBeFalse();
});

it('verifies the parental PIN and locks after 5 wrong attempts', function () {
    enableParentalControl('9999');

    $this->postJson('/api/v1/parental-control/verify', ['pin' => '9999'])->assertNoContent();

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/parental-control/verify', ['pin' => '0000'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.pin.0', 'Code PIN parental incorrect.');
    }

    // Even the right PIN is refused while locked, on every endpoint using it.
    $this->postJson('/api/v1/parental-control/verify', ['pin' => '9999'])->assertTooManyRequests();
    $this->withHeader('X-Parental-Pin', '9999')->deleteJson("/api/v1/profiles/{$this->kid->id}")->assertTooManyRequests();
});

it('rejects verification when parental control is off', function () {
    $this->postJson('/api/v1/parental-control/verify', ['pin' => '1234'])->assertUnprocessable();
});

describe('with parental control on', function () {
    beforeEach(fn () => enableParentalControl('9999'));

    it('requires the parental PIN to change restrictions', function (array $change) {
        $this->patchJson("/api/v1/profiles/{$this->kid->id}", $change)
            ->assertUnprocessable()
            ->assertJsonPath('errors.parental_pin.0', 'Le code PIN parental est obligatoire.');

        $this->patchJson("/api/v1/profiles/{$this->kid->id}", [...$change, 'parental_pin' => '0000'])
            ->assertJsonPath('errors.parental_pin.0', 'Code PIN parental incorrect.');

        $this->patchJson("/api/v1/profiles/{$this->kid->id}", [...$change, 'parental_pin' => '9999'])->assertOk();
    })->with([
        'leave kid mode' => [['is_kid' => false]],
        'raise the age limit' => [['max_age_rating' => 18]],
        'remove the age limit' => [['max_age_rating' => null]],
        'allow unrated titles' => [['allow_unrated' => true]],
        'change locked sources' => [['locked_sources' => ['jellyfin:abc']]],
    ]);

    it('does not require it for harmless changes', function () {
        $this->kid->update(['locked_sources' => ['jellyfin:a', 'jellyfin:b#Films']]);

        $this->patchJson("/api/v1/profiles/{$this->kid->id}", [
            'name' => 'Léa B.', 'avatar' => 'arcade-cat', 'position' => 2,
            // Same values as stored: not a change.
            'is_kid' => true, 'max_age_rating' => 10, 'locked_sources' => ['jellyfin:b#Films', 'jellyfin:a'],
        ])->assertOk()->assertJsonPath('data.name', 'Léa B.');
    });

    it('requires the parental PIN to create or delete a profile', function () {
        $this->postJson('/api/v1/profiles', ['name' => 'Adulte caché'])
            ->assertJsonValidationErrors('parental_pin');
        $this->postJson('/api/v1/profiles', ['name' => 'Invité', 'parental_pin' => '9999'])->assertCreated();

        $this->deleteJson("/api/v1/profiles/{$this->kid->id}")->assertJsonValidationErrors('parental_pin');
        $this->withHeader('X-Parental-Pin', '0000')->deleteJson("/api/v1/profiles/{$this->kid->id}")
            ->assertJsonPath('errors.parental_pin.0', 'Code PIN parental incorrect.');
        $this->withHeader('X-Parental-Pin', '9999')->deleteJson("/api/v1/profiles/{$this->kid->id}")->assertNoContent();
    });

    it('lets the parental PIN reset a forgotten profile PIN', function () {
        $this->patchJson("/api/v1/profiles/{$this->adult->id}", ['pin' => null])
            ->assertJsonValidationErrors('current_pin');

        $this->patchJson("/api/v1/profiles/{$this->adult->id}", ['pin' => null, 'parental_pin' => '9999'])
            ->assertOk()->assertJsonPath('data.has_pin', false);
    });
});

it('exposes the restrictions on profiles', function () {
    $this->getJson("/api/v1/profiles/{$this->kid->id}")
        ->assertJsonPath('data.is_kid', true)
        ->assertJsonPath('data.max_age_rating', 10)
        ->assertJsonPath('data.effective_max_age_rating', 10)
        ->assertJsonPath('data.allow_unrated', false)
        ->assertJsonPath('data.locked_sources', []);

    $kid = Profile::factory()->for($this->user)->create(['is_kid' => true]);
    $this->getJson("/api/v1/profiles/{$kid->id}")
        ->assertJsonPath('data.max_age_rating', null)
        ->assertJsonPath('data.effective_max_age_rating', Profile::DEFAULT_KID_MAX_AGE);
});

it('validates locked sources', function () {
    $this->patchJson("/api/v1/profiles/{$this->kid->id}", ['locked_sources' => ['jellyfin:a', 'jellyfin:a']])
        ->assertJsonValidationErrors('locked_sources.1');
});
