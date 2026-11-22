<?php

use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function messagingProfile(string $username): array
{
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['username' => $username]);

    return [$user, $profile];
}

it('handles message requests, acceptance, read receipts and blocking', function () {
    [$aliceUser, $alice] = messagingProfile('alice');
    [$bobUser, $bob] = messagingProfile('bob');

    Sanctum::actingAs($aliceUser);
    $conversation = $this->withHeader('X-Profile-Id', (string) $alice->id)
        ->postJson('/api/v1/messages/with/bob')
        ->assertOk()
        ->json('conversation_id');

    $this->withHeader('X-Profile-Id', (string) $alice->id)
        ->postJson("/api/v1/messages/{$conversation}", ['body' => 'Salut Bob'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Salut Bob');

    Sanctum::actingAs($bobUser);
    $this->withHeader('X-Profile-Id', (string) $bob->id)
        ->getJson('/api/v1/messages/requests')
        ->assertOk()
        ->assertJsonPath('data.0.sender.username', 'alice')
        ->assertJsonPath('data.0.preview', 'Salut Bob');

    $this->withHeader('X-Profile-Id', (string) $bob->id)
        ->postJson("/api/v1/messages/{$conversation}/accept")
        ->assertOk()
        ->assertJsonPath('accepted', true);

    $this->withHeader('X-Profile-Id', (string) $bob->id)
        ->getJson("/api/v1/messages/{$conversation}")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Salut Bob');

    $this->withHeader('X-Profile-Id', (string) $bob->id)
        ->postJson("/api/v1/messages/{$conversation}/read")
        ->assertOk();

    Sanctum::actingAs($aliceUser);
    $this->withHeader('X-Profile-Id', (string) $alice->id)
        ->getJson("/api/v1/messages/{$conversation}")
        ->assertOk()
        ->assertJsonPath('data.0.read_by.0', $bob->id);

    Sanctum::actingAs($bobUser);
    $this->withHeader('X-Profile-Id', (string) $bob->id)
        ->postJson('/api/v1/messages/block/alice')
        ->assertOk();

    Sanctum::actingAs($aliceUser);
    $this->withHeader('X-Profile-Id', (string) $alice->id)
        ->postJson("/api/v1/messages/{$conversation}", ['body' => 'Encore un message'])
        ->assertForbidden();
});

it('creates group conversations and restricts them to their members', function () {
    [$aliceUser, $alice] = messagingProfile('alice');
    [, $bob] = messagingProfile('bob');
    [, $carol] = messagingProfile('carol');

    Sanctum::actingAs($aliceUser);
    $conversation = $this->withHeader('X-Profile-Id', (string) $alice->id)
        ->postJson('/api/v1/messages/groups', ['name' => 'Cinéma', 'usernames' => ['bob', 'carol']])
        ->assertCreated()
        ->json('conversation_id');

    $this->withHeader('X-Profile-Id', (string) $alice->id)
        ->postJson("/api/v1/messages/{$conversation}", ['body' => 'Bienvenue !'])
        ->assertCreated();

    Sanctum::actingAs($bob->user);
    $this->withHeader('X-Profile-Id', (string) $bob->id)
        ->getJson("/api/v1/messages/{$conversation}")
        ->assertOk()
        ->assertJsonPath('conversation.type', 'group')
        ->assertJsonPath('conversation.name', 'Cinéma')
        ->assertJsonPath('data.1.body', 'Bienvenue !');

    [$outsiderUser, $outsider] = messagingProfile('david');
    Sanctum::actingAs($outsiderUser);
    $this->withHeader('X-Profile-Id', (string) $outsider->id)
        ->getJson("/api/v1/messages/{$conversation}")
        ->assertNotFound();
});
