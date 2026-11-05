<?php

use App\Models\Profile;
use App\Models\ProfileSearch;
use App\Models\User;
use App\Services\AccountExport;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->profile = Profile::factory()->for($this->user)->create(['name' => 'Davy']);
});

it('gives the playback settings of a new profile: original version, no subtitles, nothing skipped', function () {
    $this->getJson("/api/v1/profiles/{$this->profile->id}")
        ->assertOk()
        ->assertJsonPath('data.audio_language', null)
        ->assertJsonPath('data.subtitle_language', null)
        ->assertJsonPath('data.skip_intro', false)
        ->assertJsonPath('data.skip_recap', false)
        ->assertJsonPath('data.skip_credits', false)
        ->assertJsonPath('data.skip_preview', false);
});

it('saves the playback settings, without the parental PIN', function () {
    $this->user->forceFill(['parental_pin' => '1234'])->save();
    $kid = Profile::factory()->for($this->user)->kid()->create();

    $this->patchJson("/api/v1/profiles/{$kid->id}", [
        'audio_language' => 'fr',
        'subtitle_language' => 'pt-BR',
        'skip_intro' => true,
        'skip_credits' => true,
    ])->assertOk()
        ->assertJsonPath('data.audio_language', 'fr')
        ->assertJsonPath('data.subtitle_language', 'pt-BR')
        ->assertJsonPath('data.skip_intro', true)
        ->assertJsonPath('data.skip_recap', false)
        ->assertJsonPath('data.skip_credits', true);

    // Back to the original version, subtitles off.
    $this->patchJson("/api/v1/profiles/{$kid->id}", ['audio_language' => null, 'subtitle_language' => null])
        ->assertOk()
        ->assertJsonPath('data.audio_language', null)
        ->assertJsonPath('data.subtitle_language', null);
});

it('refuses unknown languages, in French', function () {
    $this->patchJson("/api/v1/profiles/{$this->profile->id}", ['audio_language' => 'xx', 'subtitle_language' => 'klingon'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.audio_language.0', "La valeur sélectionnée pour langue audio n'est pas valide.")
        ->assertJsonPath('errors.subtitle_language.0', "La valeur sélectionnée pour langue des sous-titres n'est pas valide.");
});

it('keeps recent searches, newest first, the same for every device', function () {
    $url = "/api/v1/profiles/{$this->profile->id}/searches";

    Carbon::setTestNow('2026-11-07 10:00:00');
    $this->postJson($url, ['query' => '  Dune   partie deux '])->assertCreated()->assertJsonPath('data.query', 'Dune partie deux');
    Carbon::setTestNow('2026-11-07 11:00:00');
    $this->postJson($url, ['query' => 'Orbite'])->assertCreated();
    // Searched again, another case: one entry, back on top.
    Carbon::setTestNow('2026-11-07 12:00:00');
    $this->postJson($url, ['query' => 'dune PARTIE deux'])->assertOk();

    $this->getJson($url)->assertOk()->assertJsonPath('data.*.query', ['dune PARTIE deux', 'Orbite']);
});

it('replaces a search typed just before when it extends it', function () {
    $url = "/api/v1/profiles/{$this->profile->id}/searches";

    Carbon::setTestNow('2026-11-07 10:00:00');
    $this->postJson($url, ['query' => 'le'])->assertCreated();
    // Long ago: kept.
    Carbon::setTestNow('2026-11-07 11:00:00');
    $this->postJson($url, ['query' => 'orb'])->assertCreated();
    Carbon::setTestNow('2026-11-07 11:00:20');
    $this->postJson($url, ['query' => 'orbite'])->assertCreated();
    Carbon::setTestNow('2026-11-07 11:00:30');
    $this->postJson($url, ['query' => 'orbite 9'])->assertCreated();

    $this->getJson($url)->assertJsonPath('data.*.query', ['orbite 9', 'le']);
});

it('keeps the 20 most recent searches', function () {
    $url = "/api/v1/profiles/{$this->profile->id}/searches";
    foreach (range(1, 22) as $i) {
        Carbon::setTestNow(now()->addHour());
        $this->postJson($url, ['query' => "recherche {$i}"])->assertCreated();
    }

    $queries = $this->getJson($url)->assertJsonCount(20, 'data')->json('data.*.query');
    expect($queries[0])->toBe('recherche 22')->and($queries[19])->toBe('recherche 3')
        ->and(ProfileSearch::count())->toBe(20);
});

it('removes one search or all of them', function () {
    $url = "/api/v1/profiles/{$this->profile->id}/searches";
    $first = $this->postJson($url, ['query' => 'Dune'])->json('data.id');
    Carbon::setTestNow(now()->addHour());
    $this->postJson($url, ['query' => 'Orbite']);

    $this->deleteJson("{$url}/{$first}")->assertNoContent();
    $this->getJson($url)->assertJsonPath('data.*.query', ['Orbite']);

    $this->postJson("{$url}/clear")->assertNoContent();
    $this->getJson($url)->assertJsonCount(0, 'data');
});

it('keeps searches to their profile and account', function () {
    $other = Profile::factory()->create();
    $this->getJson("/api/v1/profiles/{$other->id}/searches")->assertForbidden();
    $this->postJson("/api/v1/profiles/{$other->id}/searches", ['query' => 'Dune'])->assertForbidden();

    // A search of another profile of the account can't be removed through this one.
    $sibling = Profile::factory()->for($this->user)->create();
    $id = $this->postJson("/api/v1/profiles/{$sibling->id}/searches", ['query' => 'Dune'])->json('data.id');
    $this->deleteJson("/api/v1/profiles/{$this->profile->id}/searches/{$id}")->assertNotFound();
});

it('validates searches in French', function () {
    $response = $this->postJson("/api/v1/profiles/{$this->profile->id}/searches", ['query' => ' a '])->assertUnprocessable();
    expect($response->json('errors.query.0'))->toContain('recherche')->toContain('2');
});

it('deletes searches with the profile and exports them', function () {
    $this->postJson("/api/v1/profiles/{$this->profile->id}/searches", ['query' => 'Dune']);
    $this->profile->update(['subtitle_language' => 'fr', 'skip_intro' => true]);

    $export = app(AccountExport::class)->build($this->user->fresh());
    expect($export['profiles'][0]['recent_searches'][0]['query'])->toBe('Dune')
        ->and($export['profiles'][0]['subtitle_language'])->toBe('fr')
        ->and($export['profiles'][0]['skip_intro'])->toBeTrue();

    $this->profile->delete();
    expect(ProfileSearch::count())->toBe(0);
});
