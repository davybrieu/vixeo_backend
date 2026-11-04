<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

it('translates validation errors and field names', function () {
    $this->postJson('/api/v1/auth/register', ['email' => 'not-an-email', 'password' => 'short'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Le champ nom est obligatoire.')
        ->assertJsonPath('errors.email.0', 'Le champ adresse e-mail doit être une adresse e-mail valide.')
        ->assertJsonPath('errors.password.0', 'Le champ mot de passe doit contenir au moins 8 caractères.')
        ->assertJsonPath('errors.device_name.0', "Le champ nom de l'appareil est obligatoire.");
});

it('uses custom messages for duplicates', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Davy', 'email' => 'taken@example.com', 'password' => 'secret-password', 'device_name' => 'test',
    ])->assertJsonPath('errors.email.0', 'Un compte existe déjà avec cette adresse e-mail.');

    $user = User::factory()->create();
    Profile::factory()->for($user)->create(['name' => 'Léa']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/profiles', ['name' => 'Léa'])
        ->assertJsonPath('errors.name.0', 'Un profil porte déjà ce nom.');
});

it('translates failed login', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'x', 'device_name' => 'test'])
        ->assertJsonPath('errors.email.0', 'Adresse e-mail ou mot de passe incorrect.');
});

it('translates 401', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertExactJson(['message' => 'Non authentifié.']);
});

it('translates 403, including policy messages', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/profiles/'.Profile::factory()->create()->id)
        ->assertForbidden()->assertExactJson(['message' => 'Action non autorisée.']);

    $last = Profile::factory()->for($user)->create();
    $this->deleteJson("/api/v1/profiles/{$last->id}")
        ->assertForbidden()->assertExactJson(['message' => "Le dernier profil d'un compte ne peut pas être supprimé."]);

    Profile::factory()->for($user)->count(Profile::MAX_PER_USER - 1)->create();
    $this->postJson('/api/v1/profiles', ['name' => 'Trop'])
        ->assertForbidden()->assertExactJson(['message' => 'Un compte ne peut pas avoir plus de 5 profils.']);
});

it('translates 404 without leaking model names', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/profiles/999')->assertNotFound()->assertExactJson(['message' => 'Ressource introuvable.']);
    $this->getJson('/api/v1/nope')->assertNotFound()->assertExactJson(['message' => 'Ressource introuvable.']);
});

it('translates 405', function () {
    $this->putJson('/api/v1/auth/login')->assertMethodNotAllowed()->assertExactJson(['message' => 'Méthode non autorisée.']);
});

it('translates 429 with the waiting time', function () {
    $payload = ['email' => 'x@example.com', 'password' => 'x', 'device_name' => 'test'];
    foreach (range(1, 10) as $i) {
        $this->postJson('/api/v1/auth/login', $payload);
    }

    $response = $this->postJson('/api/v1/auth/login', $payload)->assertTooManyRequests();

    expect($response->json('message'))->toMatch('/^Trop de tentatives\. Réessayez dans \d+ secondes\.$/');
    $response->assertHeader('Retry-After');
});

it('translates the PIN error', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->withPin('1234')->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/profiles/{$profile->id}/verify-pin", ['pin' => '0000'])
        ->assertJsonPath('errors.pin.0', 'Code PIN incorrect.');
});

it('hides unexpected errors behind a French message outside debug mode', function () {
    config(['app.debug' => false]);
    Route::get('api/v1/_boom', fn () => throw new RuntimeException('secret details'));

    $this->getJson('/api/v1/_boom')->assertStatus(500)->assertExactJson(['message' => 'Erreur interne du serveur.']);
});
