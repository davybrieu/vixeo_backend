<?php

use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('lists the account single profile', function () {
    $profile = Profile::factory()->for($this->user)->create(['name' => 'Davy']);
    Profile::factory()->create(['name' => 'Someone else']);

    $this->getJson('/api/v1/profiles')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $profile->id)
        ->assertJsonPath('data.0.name', 'Davy')
        ->assertJsonMissingPath('data.0.is_kid')
        ->assertJsonMissingPath('data.0.max_age_rating')
        ->assertJsonMissingPath('data.0.has_pin');
});

it('creates the first and only profile', function () {
    $this->postJson('/api/v1/profiles', [
        'name' => 'Léa',
        'username' => 'lea',
        'is_kid' => true,
        'max_age_rating' => 10,
        'allow_unrated' => false,
        'pin' => '4321',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Léa')
        ->assertJsonMissingPath('data.is_kid')
        ->assertJsonMissingPath('data.max_age_rating')
        ->assertJsonMissingPath('data.has_pin');

    expect($this->user->profiles()->count())->toBe(1);
});

it('rejects creation of a second profile', function () {
    Profile::factory()->for($this->user)->create();

    $this->postJson('/api/v1/profiles', ['name' => 'Second profile'])->assertForbidden();
    expect($this->user->profiles()->count())->toBe(1);
});

it('rejects duplicate profile names within an account', function () {
    Profile::factory()->for($this->user)->create(['name' => 'Davy']);

    $this->postJson('/api/v1/profiles', ['name' => 'Davy'])
        ->assertForbidden();
});

it('updates the one profile without PIN or age restriction fields', function () {
    $profile = Profile::factory()->for($this->user)->create(['name' => 'Davy']);

    $this->patchJson("/api/v1/profiles/{$profile->id}", [
        'name' => 'Renamed',
        'is_kid' => true,
        'max_age_rating' => 10,
        'allow_unrated' => false,
        'pin' => '1234',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonMissingPath('data.is_kid')
        ->assertJsonMissingPath('data.max_age_rating')
        ->assertJsonMissingPath('data.has_pin');
});

it('keeps a biography to 160 characters', function () {
    $profile = Profile::factory()->for($this->user)->create(['name' => 'Davy']);

    $this->patchJson("/api/v1/profiles/{$profile->id}", ['bio' => str_repeat('é', 160)])
        ->assertOk()
        ->assertJsonPath('data.bio', str_repeat('é', 160));

    $this->patchJson("/api/v1/profiles/{$profile->id}", ['bio' => str_repeat('a', 161)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.bio.0', 'Le champ biographie ne doit pas contenir plus de 160 caractères.');
});


it('requires a name, username, avatar and banner before first-time registration can be completed', function () {
    $profile = Profile::factory()->for($this->user)->create([
        'name' => 'Moi',
        'username' => 'moi',
        'avatar' => app(\App\Services\AvatarCatalog::class)->randomId(),
        'cover' => app(\App\Services\CoverCatalog::class)->randomId(),
    ]);

    $this->patchJson("/api/v1/profiles/{$profile->id}", ['registration_completed' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'username', 'avatar', 'cover']);

    $this->patchJson("/api/v1/profiles/{$profile->id}", [
        'name' => 'Davy',
        'username' => 'davy',
        'avatar' => app(\App\Services\AvatarCatalog::class)->randomId(),
        'cover' => app(\App\Services\CoverCatalog::class)->randomId(),
        'registration_completed' => true,
    ])->assertOk()
        ->assertJsonPath('data.registration_completed', true);
});

it('forbids access to another account profile and exposes no delete or PIN routes', function () {
    $other = Profile::factory()->create();

    $this->getJson("/api/v1/profiles/{$other->id}")->assertForbidden();
    $this->patchJson("/api/v1/profiles/{$other->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->deleteJson("/api/v1/profiles/{$other->id}")->assertMethodNotAllowed();
    $this->postJson("/api/v1/profiles/{$other->id}/verify-pin", ['pin' => '1234'])->assertNotFound();
});
