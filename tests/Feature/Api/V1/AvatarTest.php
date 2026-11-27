<?php

use App\Models\Profile;
use App\Models\User;
use App\Services\AvatarCatalog;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = Profile::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user);
});

it('lists avatars grouped by license, sorted by name', function () {
    $response = $this->getJson('/api/v1/avatars')->assertOk();

    $licenses = $response->json('data');
    $names = array_column($licenses, 'name');
    $sorted = $names;
    sort($sorted);

    expect($names)->toBe($sorted)
        ->and(count($licenses))->toBeGreaterThan(0);

    foreach ($licenses as $license) {
        $avatarNames = array_column($license['avatars'], 'name');
        $collator = new Collator('fr_FR');
        $expected = $avatarNames;
        usort($expected, fn ($a, $b) => $collator->compare($a, $b));
        expect($avatarNames)->toBe($expected);
    }

    expect($response->json('data.0.avatars.0'))->toHaveKeys(['id', 'name', 'background', 'image_url', 'pixels', 'palette']);
});

it('ships valid 12x12 pixel art with known colors and unique ids', function () {
    $ids = [];
    foreach (app(AvatarCatalog::class)->licenses() as $license) {
        foreach ($license['avatars'] as $avatar) {
            $ids[] = $avatar['id'];
            if (isset($avatar['image_url'])) {
                continue;
            }
            expect($avatar['pixels'])->toHaveCount(12);
            foreach ($avatar['pixels'] as $row) {
                expect(mb_strlen($row))->toBe(12);
                foreach (mb_str_split(str_replace('.', '', $row)) as $char) {
                    expect($avatar['palette'])->toHaveKey($char);
                }
            }
        }
    }

    expect($ids)->toBe(array_unique($ids));
});

it('exposes the read-only avatar catalog to first-time registrants', function () {
    $this->getJson('/api/v1/avatars')->assertOk()->assertJsonStructure(['data']);
});

it('sets a profile avatar from the catalog', function () {
    $this->patchJson("/api/v1/profiles/{$this->profile->id}", ['avatar' => 'galaxy-robot'])
        ->assertOk()
        ->assertJsonPath('data.avatar', 'galaxy-robot');

    $this->patchJson("/api/v1/profiles/{$this->profile->id}", ['avatar' => null])
        ->assertOk()
        ->assertJsonPath('data.avatar', null);
});

it('rejects an unknown avatar', function () {
    $this->patchJson("/api/v1/profiles/{$this->profile->id}", ['avatar' => 'mario'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.avatar.0', "La valeur sélectionnée pour avatar n'est pas valide.");
});

it('has no poster format any more: the apps show vertical posters only', function () {
    $this->getJson("/api/v1/profiles/{$this->profile->id}")->assertJsonMissingPath('data.poster_format');
    // An app of before still sends it: ignored.
    $this->patchJson("/api/v1/profiles/{$this->profile->id}", ['poster_format' => 'landscape'])
        ->assertOk()
        ->assertJsonMissingPath('data.poster_format');
});

it('updates the account profile avatar without a PIN', function () {
    $this->patchJson("/api/v1/profiles/{$this->profile->id}", ['avatar' => 'arcade-cat'])->assertOk();
});

it('does not allow a second profile to be created', function () {
    $this->postJson('/api/v1/profiles', ['name' => 'Second profile'])->assertForbidden();
});
it('does not allow the legacy registration shortcut to create an account before profile completion', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Nouveau',
        'email' => 'nouveau@exemple.fr',
        'username' => 'nouveau',
        'redirect_uri' => 'app.vixeo://auth',
        'code_challenge' => str_repeat('a', 43),
    ])->assertNotFound();

    expect(User::where('email', 'nouveau@exemple.fr')->exists())->toBeFalse();
});
