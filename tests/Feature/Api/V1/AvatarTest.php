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

it('requires authentication', function () {
    app('auth')->forgetGuards();
    $this->withHeaders(['Authorization' => 'Bearer nope'])->getJson('/api/v1/avatars')->assertUnauthorized();
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

it('stores the poster format, portrait by default', function () {
    $this->getJson("/api/v1/profiles/{$this->profile->id}")->assertJsonPath('data.poster_format', 'portrait');

    $this->patchJson("/api/v1/profiles/{$this->profile->id}", ['poster_format' => 'landscape'])
        ->assertOk()
        ->assertJsonPath('data.poster_format', 'landscape');

    $this->patchJson("/api/v1/profiles/{$this->profile->id}", ['poster_format' => 'square'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.poster_format.0', "La valeur sélectionnée pour format des affiches n'est pas valide.");
});

it('does not need the parental PIN to change avatar or poster format', function () {
    $this->putJson('/api/v1/parental-control/pin', ['pin' => '9999'])->assertNoContent();

    $this->patchJson("/api/v1/profiles/{$this->profile->id}", ['avatar' => 'arcade-cat', 'poster_format' => 'landscape'])
        ->assertOk();
});

it('gives a new profile an avatar of the catalog at random, unless one is chosen', function () {
    $ids = app(AvatarCatalog::class)->ids();

    $avatar = $this->postJson('/api/v1/profiles', ['name' => 'Léa'])->assertCreated()->json('data.avatar');
    expect($ids)->toContain($avatar);

    $this->postJson('/api/v1/profiles', ['name' => 'Tom', 'avatar' => null])->assertCreated()
        ->assertJsonPath('data.avatar', fn ($a) => in_array($a, $ids, true));
    $this->postJson('/api/v1/profiles', ['name' => 'Zoé', 'avatar' => 'arcade-cat'])->assertCreated()
        ->assertJsonPath('data.avatar', 'arcade-cat');

    // Not always the same one.
    $picked = collect(range(1, 40))->map(fn () => app(AvatarCatalog::class)->randomId())->unique();
    expect($picked->count())->toBeGreaterThan(1);
});

it('gives the first profile of a new account an avatar of the catalog', function () {
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Davy',
        'email' => 'nouveau@exemple.fr',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'device_name' => 'Test',
    ])->assertCreated();

    $profile = User::where('email', 'nouveau@exemple.fr')->first()->profiles()->first();
    expect(app(AvatarCatalog::class)->ids())->toContain($profile->avatar);
});
