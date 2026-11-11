<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('registers a user with a first profile and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Davy',
        'email' => 'davy@example.com',
        'password' => 'secret-password',
        'device_name' => 'Pixel 8',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'email_verified', 'created_at']])
        ->assertJsonMissingPath('user.password');

    $user = User::where('email', 'davy@example.com')->firstOrFail();
    expect($user->profiles)->toHaveCount(1)
        ->and($user->profiles->first()->name)->toBe('Davy')
        ->and($user->tokens()->first()->name)->toBe('Pixel 8');
});

it('validates registration input', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'taken@example.com',
        'password' => 'short',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name']);
});

it('logs in with valid credentials', function () {
    User::factory()->create(['email' => 'davy@example.com', 'password' => 'secret-password']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'davy@example.com',
        'password' => 'secret-password',
        'device_name' => 'Salon TV',
    ])->assertOk()->assertJsonStructure(['token', 'user' => ['id']]);
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'davy@example.com', 'password' => 'secret-password']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'davy@example.com',
        'password' => 'wrong-password',
        'device_name' => 'Salon TV',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('throttles login attempts', function () {
    $payload = ['email' => 'nobody@example.com', 'password' => 'x', 'device_name' => 'test'];

    foreach (range(1, 10) as $i) {
        $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', $payload)->assertTooManyRequests();
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('protects personal endpoints while allowing public catalog configuration', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
    $this->getJson('/api/v1/profiles')->assertUnauthorized();
    $this->getJson('/api/v1/catalog/config')->assertOk();
    $this->withToken('invalid-token')
        ->withHeader('X-Profile-Id', '1')
        ->getJson('/api/v1/catalog/home')
        ->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('lets sanctum acting-as users reach protected routes', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me')->assertOk();
});
