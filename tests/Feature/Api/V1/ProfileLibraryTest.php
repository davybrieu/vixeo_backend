<?php

use App\Enums\ItemType;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = Profile::factory()->for($this->user)->create();
    $this->url = "/api/v1/profiles/{$this->profile->id}";
    Sanctum::actingAs($this->user);
});

it('lists continue watching, most recent first, without finished or dismissed items', function () {
    $items = ProfileItem::factory()->for($this->profile);
    $items->inProgress(at: '2026-01-01 10:00:00')->create(['title' => 'Older']);
    $items->inProgress(at: '2026-01-02 10:00:00')->create(['title' => 'Newer', 'type' => ItemType::Episode]);
    $items->inProgress(at: '2026-01-03 10:00:00')->watched()->create(['title' => 'Finished']);
    $items->create(['title' => 'Dismissed', 'position_ms' => 1000, 'last_engaged_at' => null]);

    $this->getJson("{$this->url}/continue-watching")
        ->assertOk()
        ->assertJsonPath('data.*.title', ['Newer', 'Older'])
        ->assertJsonPath('data.0.progress', 0.1);

    $this->getJson("{$this->url}/continue-watching?type=episode")->assertJsonPath('data.*.title', ['Newer']);
});

it('lists favorites with filters and sorts', function () {
    $items = ProfileItem::factory()->for($this->profile);
    $items->favorite('2026-01-01')->create(['title' => 'bravo', 'year' => 2001]);
    $items->favorite('2026-01-03')->create(['title' => 'Alpha', 'year' => null]);
    $items->favorite('2026-01-02')->watched()->create(['title' => 'Charlie', 'year' => 2020, 'type' => ItemType::Tv]);
    $items->create(['title' => 'Not a favorite']);

    $this->getJson("{$this->url}/favorites")->assertJsonPath('data.*.title', ['Alpha', 'Charlie', 'bravo'])
        ->assertJsonPath('meta.total', 3);
    $this->getJson("{$this->url}/favorites?sort=title")->assertJsonPath('data.*.title', ['Alpha', 'bravo', 'Charlie']);
    $this->getJson("{$this->url}/favorites?sort=year")->assertJsonPath('data.*.title', ['Charlie', 'bravo', 'Alpha']);
    $this->getJson("{$this->url}/favorites?type=tv")->assertJsonPath('data.*.title', ['Charlie']);
    $this->getJson("{$this->url}/favorites?watched=0")->assertJsonPath('data.*.title', ['Alpha', 'bravo']);
    $this->getJson("{$this->url}/favorites?per_page=1&page=2")->assertJsonPath('data.*.title', ['Charlie']);
});

it('lists the watch history', function () {
    $items = ProfileItem::factory()->for($this->profile);
    $items->watched('2026-01-01')->create(['title' => 'First']);
    $items->watched('2026-01-05')->create(['title' => 'Last']);
    $items->inProgress()->create(['title' => 'In progress']);

    $this->getJson("{$this->url}/history")->assertOk()->assertJsonPath('data.*.title', ['Last', 'First']);
});

it('validates library filters', function () {
    $this->getJson("{$this->url}/favorites?sort=random&per_page=500")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort', 'per_page']);
});
