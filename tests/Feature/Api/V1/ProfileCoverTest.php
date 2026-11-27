<?php

use App\Models\Profile;
use App\Models\Report;
use App\Models\SocialNotification;
use App\Models\User;
use App\Services\CoverCatalog;
use App\Services\ProfileCovers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

// The cover of a profile (developer's request, 24/11/2026): the banner of its page, a cover of the
// catalog (several styles, one at random for a new profile) or its own image.

beforeEach(function () {
    Storage::fake(ProfileCovers::DISK);
});

function coverMember(string $username, array $attributes = []): Profile
{
    return Profile::factory()->for(User::factory()->create(['password' => 'secret-password']))
        ->create(['username' => $username, 'cover' => 'aurore', ...$attributes]);
}

function asCoverMember(Profile $profile): mixed
{
    Sanctum::actingAs($profile->user);

    return test();
}

function sendCover(Profile $profile, UploadedFile $cover): mixed
{
    return asCoverMember($profile)->post("/api/v1/profiles/{$profile->id}/cover", ['cover' => $cover], ['Accept' => 'application/json']);
}

it('lists the covers by style, each with its pattern and its colors', function () {
    $lea = coverMember('lea');

    $styles = asCoverMember($lea)->getJson('/api/v1/covers')->assertOk()->json('data');

    expect(array_column($styles, 'name'))->toBe(['Dégradés', 'Lumières', 'Motifs', 'Cinéma'])
        ->and($styles[0]['covers'][0])->toBe([
            'id' => 'aurore',
            'name' => 'Aurore',
            'pattern' => 'linear',
            'colors' => ['#03140A', '#0E5C3A', '#2EE58A'],
            'angle' => 160,
        ]);
    $patterns = array_unique(array_merge(...array_map(fn ($s) => array_column($s['covers'], 'pattern'), $styles)));
    expect($patterns)->toContain('glow', 'stripes', 'film', 'stars');
    // Every color is #RRGGBB.
    foreach ($styles as $style) {
        foreach ($style['covers'] as $cover) {
            expect($cover['colors'])->not->toBeEmpty()->each->toMatch('/^#[0-9A-F]{6}$/');
        }
    }
});

it('gives a cover of the catalog, at random, to a new account and to a new profile', function () {
    $ids = app(CoverCatalog::class)->ids();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Léa', 'email' => 'lea@example.com', 'password' => 'secret-password',
        'password_confirmation' => 'secret-password', 'device_name' => 'test',
    ])->assertCreated();
    $first = Profile::query()->firstOrFail();
    expect($first->cover)->toBeIn($ids);

    Sanctum::actingAs($first->user);
    $created = $this->postJson('/api/v1/profiles', ['name' => 'Max'])->assertCreated();
    expect($created->json('data.cover'))->toBeIn($ids)
        ->and($created->json('data.cover_url'))->toBeNull();

    // One chosen.
    $this->postJson('/api/v1/profiles', ['name' => 'Zoé', 'cover' => 'pellicule'])->assertCreated()
        ->assertJsonPath('data.cover', 'pellicule');
});

it('changes the cover for another of the catalog, never an unknown one', function () {
    $lea = coverMember('lea');

    asCoverMember($lea)->putJson("/api/v1/profiles/{$lea->id}", ['cover' => 'rideau'])->assertOk()
        ->assertJsonPath('data.cover', 'rideau');
    asCoverMember($lea)->putJson("/api/v1/profiles/{$lea->id}", ['cover' => 'inconnue'])->assertUnprocessable()
        ->assertJsonValidationErrors(['cover' => 'couverture']);
});

it('keeps the central 3:1 part of an image, 1,500 pixels wide at most, and serves it to anyone', function () {
    $lea = coverMember('lea');

    $url = sendCover($lea, UploadedFile::fake()->image('c.jpg', 3000, 2000))->assertOk()
        ->assertJsonPath('data.cover', 'aurore')
        ->json('data.cover_url');

    $name = $lea->refresh()->cover_path;
    expect($url)->toEndWith('/api/v1/profile-covers/'.$name);
    $size = getimagesizefromstring(Storage::disk(ProfileCovers::DISK)->get(ProfileCovers::DIRECTORY.'/'.$name));
    expect([$size[0], $size[1]])->toBe([1500, 500]);

    // Public: no bearer token.
    $this->app['auth']->forgetGuards();
    $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

    // A narrower image: its full width, never enlarged.
    sendCover($lea, UploadedFile::fake()->image('c.png', 900, 900))->assertOk();
    $size = getimagesizefromstring(Storage::disk(ProfileCovers::DISK)->get(ProfileCovers::DIRECTORY.'/'.$lea->refresh()->cover_path));
    expect([$size[0], $size[1]])->toBe([900, 300]);
});

it('replaces the previous image, and goes back to the cover of the catalog when it is removed', function () {
    $lea = coverMember('lea');
    sendCover($lea, UploadedFile::fake()->image('c.png', 1200, 400))->assertOk();
    $first = ProfileCovers::DIRECTORY.'/'.$lea->refresh()->cover_path;

    sendCover($lea, UploadedFile::fake()->image('c.png', 1200, 400))->assertOk();
    Storage::disk(ProfileCovers::DISK)->assertMissing($first);
    $second = ProfileCovers::DIRECTORY.'/'.$lea->refresh()->cover_path;

    asCoverMember($lea)->deleteJson("/api/v1/profiles/{$lea->id}/cover")->assertOk()
        ->assertJsonPath('data.cover_url', null)
        ->assertJsonPath('data.cover', 'aurore');
    Storage::disk(ProfileCovers::DISK)->assertMissing($second);
});

it('refuses what is not an image, an image too small, and the profiles of another account', function () {
    $lea = coverMember('lea');
    $max = coverMember('max');

    sendCover($lea, UploadedFile::fake()->create('notes.txt', 10, 'text/plain'))->assertUnprocessable()
        ->assertJsonValidationErrors('cover');
    sendCover($lea, UploadedFile::fake()->image('c.png', 400, 150))->assertUnprocessable()
        ->assertJsonValidationErrors('cover');
    asCoverMember($max)->post("/api/v1/profiles/{$lea->id}/cover", ['cover' => UploadedFile::fake()->image('c.png', 1200, 400)], ['Accept' => 'application/json'])
        ->assertForbidden();
});

it('shows the cover on the page of the profile', function () {
    $lea = coverMember('lea', ['cover' => 'neon']);
    $max = coverMember('max');

    asCoverMember($max)->getJson('/api/v1/social/profiles/lea')->assertOk()
        ->assertJsonPath('data.cover', 'neon')
        ->assertJsonPath('data.cover_url', null);
    sendCover($lea, UploadedFile::fake()->image('c.png', 1200, 400))->assertOk();
    expect(asCoverMember($max)->getJson('/api/v1/social/profiles/lea')->json('data.cover_url'))
        ->toEndWith('/api/v1/profile-covers/'.$lea->refresh()->cover_path);
});

it('deletes the cover image with the account', function () {
    $lea = coverMember('lea');
    sendCover($lea, UploadedFile::fake()->image('c.png', 1200, 400));
    $path = ProfileCovers::DIRECTORY.'/'.$lea->refresh()->cover_path;

    asCoverMember($lea)->postJson('/api/v1/me/delete', ['password' => 'secret-password'])->assertNoContent();
    Storage::disk(ProfileCovers::DISK)->assertMissing($path);
});

it('lets the moderation remove a reported cover image', function () {
    $lea = coverMember('lea');
    $max = coverMember('max');
    sendCover($lea, UploadedFile::fake()->image('c.png', 1200, 400));
    asCoverMember($max)->postJson('/api/v1/social/reports', ['type' => 'profile', 'id' => $lea->id, 'reason' => 'sexual'])->assertSuccessful();
    $report = Report::query()->value('id');

    $this->artisan('vixeo:moderate', ['report' => $report, 'action' => 'cover', '--reason' => 'Image inappropriée'])
        ->expectsOutputToContain('image de couverture retirée')
        ->assertSuccessful();

    expect($lea->refresh()->cover_path)->toBeNull()
        ->and($lea->cover)->toBe('aurore')
        ->and(SocialNotification::query()->where('profile_id', $lea->id)->value('data'))
        ->toBe(['text' => 'Votre image de couverture a été retirée par la modération : Image inappropriée']);

    $this->artisan('vixeo:moderate', ['report' => $report, 'action' => 'cover', '--reason' => 'Encore'])->assertFailed();
});
