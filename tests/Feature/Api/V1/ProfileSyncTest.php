<?php

use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-06-01 12:00:00');
    $this->user = User::factory()->create();
    $this->profile = Profile::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user);
});

function change(array $overrides = []): array
{
    return array_merge([
        'source' => 'tmdb',
        'item_id' => 'movie:550',
        'type' => 'movie',
        'title' => 'Fight Club',
        'year' => 1999,
        'poster_url' => 'https://image.tmdb.org/t/p/w342/poster.jpg',
    ], $overrides);
}

function push(array $changes)
{
    return test()->postJson('/api/v1/profiles/'.test()->profile->id.'/sync', ['changes' => $changes]);
}

function pull(int $cursor = 0, ?int $limit = null)
{
    $query = http_build_query(array_filter(['cursor' => $cursor, 'limit' => $limit], fn ($v) => $v !== null));

    return test()->getJson('/api/v1/profiles/'.test()->profile->id.'/sync?'.$query);
}

it('stores favorites, progress and watched state', function () {
    push([change([
        'favorite' => ['value' => true, 'at' => '2026-06-01T10:00:00Z'],
        'progress' => ['position_ms' => 1_800_000, 'duration_ms' => 8_340_000, 'continue_watching' => true, 'at' => '2026-06-01T10:30:00.250Z'],
    ])])
        ->assertOk()
        ->assertJsonPath('revision', 1)
        ->assertJsonPath('data.0.is_favorite', true)
        ->assertJsonPath('data.0.favorited_at', '2026-06-01T10:00:00.000000Z')
        ->assertJsonPath('data.0.position_ms', 1_800_000)
        ->assertJsonPath('data.0.progress', 0.216)
        ->assertJsonPath('data.0.last_engaged_at', '2026-06-01T10:30:00.000000Z')
        ->assertJsonPath('data.0.is_watched', false);

    expect(ProfileItem::count())->toBe(1)
        ->and($this->profile->fresh()->sync_revision)->toBe(1);
});

it('resolves conflicts per group: the most recent change wins', function () {
    // TV: favorite at 10:00. Phone, offline: progress at 09:00 and an older "unfavorite" at 08:00.
    push([change(['favorite' => ['value' => true, 'at' => '2026-06-01T10:00:00Z']])]);

    push([change([
        'favorite' => ['value' => false, 'at' => '2026-06-01T08:00:00Z'],
        'progress' => ['position_ms' => 60_000, 'continue_watching' => true, 'at' => '2026-06-01T09:00:00Z'],
    ])])
        ->assertJsonPath('data.0.is_favorite', true)
        ->assertJsonPath('data.0.position_ms', 60_000);

    // A newer unfavorite wins.
    push([change(['favorite' => ['value' => false, 'at' => '2026-06-01T11:00:00Z']])])
        ->assertJsonPath('data.0.is_favorite', false)
        ->assertJsonPath('data.0.favorited_at', null);
});

it('ignores an older progress', function () {
    push([change(['progress' => ['position_ms' => 500_000, 'continue_watching' => true, 'at' => '2026-06-01T11:00:00Z']])]);
    push([change(['progress' => ['position_ms' => 100_000, 'continue_watching' => true, 'at' => '2026-06-01T10:00:00Z']])])
        ->assertJsonPath('data.0.position_ms', 500_000);
});

it('does not bump the revision when a push changes nothing', function () {
    $payload = [change(['watched' => ['value' => true, 'at' => '2026-06-01T10:00:00.123Z']])];

    push($payload)->assertJsonPath('revision', 1);
    push($payload)->assertJsonPath('revision', 1);

    expect(ProfileItem::first()->revision)->toBe(1);
});

it('keeps the original date when re-favoriting', function () {
    push([change(['favorite' => ['value' => true, 'at' => '2026-06-01T10:00:00Z']])]);
    push([change(['favorite' => ['value' => true, 'at' => '2026-06-01T11:00:00Z']])])
        ->assertJsonPath('data.0.favorited_at', '2026-06-01T10:00:00.000000Z')
        ->assertJsonPath('data.0.favorite_changed_at', '2026-06-01T11:00:00.000000Z');
});

it('brings timestamps from the future back to the server time', function () {
    // A box with its clock in 2030 must not win every future conflict.
    push([change(['favorite' => ['value' => true, 'at' => '2030-01-01T00:00:00Z']])])
        ->assertJsonPath('data.0.favorite_changed_at', '2026-06-01T12:00:00.000000Z');

    push([change(['favorite' => ['value' => false, 'at' => '2026-06-01T12:01:00Z']])])
        ->assertJsonPath('data.0.is_favorite', false);
});

it('removes an item from continue watching but keeps its position', function () {
    push([change(['progress' => ['position_ms' => 500_000, 'continue_watching' => true, 'at' => '2026-06-01T10:00:00Z']])]);
    push([change(['progress' => ['position_ms' => 500_000, 'continue_watching' => false, 'at' => '2026-06-01T10:05:00Z']])])
        ->assertJsonPath('data.0.last_engaged_at', null)
        ->assertJsonPath('data.0.position_ms', 500_000);
});

it('applies a batch in order, e.g. "vu jusqu\'ici" on episodes', function () {
    $episodes = collect(range(1, 3))->map(fn (int $n) => change([
        'item_id' => "episode:1399:1:{$n}", 'type' => 'episode', 'title' => "Épisode {$n}",
        'parent_item_id' => 'tv:1399', 'season_number' => 1, 'episode_number' => $n,
        'watched' => ['value' => true, 'at' => '2026-06-01T10:00:00Z'],
    ]))->all();

    push($episodes)->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('revision', 3);

    expect(ProfileItem::where('is_watched', true)->count())->toBe(3);
});

it('updates the display snapshot', function () {
    push([change(['watched' => ['value' => true, 'at' => '2026-06-01T10:00:00Z']])]);
    push([change(['title' => 'Fight Club (VF)', 'watched' => ['value' => true, 'at' => '2026-06-01T10:00:00Z']])])
        ->assertJsonPath('data.0.title', 'Fight Club (VF)')
        ->assertJsonPath('revision', 2);
});

it('pulls everything on first sync, then only what changed', function () {
    push([
        change(['item_id' => 'movie:1', 'favorite' => ['value' => true, 'at' => '2026-06-01T10:00:00Z']]),
        change(['item_id' => 'movie:2', 'watched' => ['value' => true, 'at' => '2026-06-01T10:00:00Z']]),
    ]);

    $first = pull()->assertOk()->assertJsonPath('has_more', false)->assertJsonPath('cursor', 2);
    expect($first->json('data.*.item_id'))->toBe(['movie:1', 'movie:2']);

    pull(2)->assertJsonPath('data', [])->assertJsonPath('cursor', 2);

    // Another device unfavorites movie:1.
    push([change(['item_id' => 'movie:1', 'favorite' => ['value' => false, 'at' => '2026-06-01T11:00:00Z']])]);

    pull(2)->assertJsonPath('cursor', 3)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.item_id', 'movie:1')
        ->assertJsonPath('data.0.is_favorite', false);

    // A new device does not download the now empty item.
    pull()->assertJsonCount(1, 'data')->assertJsonPath('data.0.item_id', 'movie:2')->assertJsonPath('cursor', 3);
});

it('keeps "Favoris" (liked) apart from "Ma liste", for every type', function () {
    push([
        change(['liked' => ['value' => true, 'at' => '2026-06-01T10:00:00Z']]),
        change([
            'item_id' => 'episode:1399:1:3', 'type' => 'episode', 'title' => 'Le Prix du fer',
            'parent_item_id' => 'tv:1399', 'season_number' => 1, 'episode_number' => 3,
            'liked' => ['value' => true, 'at' => '2026-06-01T10:01:00Z'],
        ]),
        change(['source' => 'tv', 'item_id' => 'channel:tf1', 'type' => 'channel', 'title' => 'TF1', 'year' => null,
            'liked' => ['value' => true, 'at' => '2026-06-01T10:02:00Z']]),
    ])
        ->assertOk()
        ->assertJsonPath('data.0.is_liked', true)
        ->assertJsonPath('data.0.liked_at', '2026-06-01T10:00:00.000000Z')
        ->assertJsonPath('data.0.is_favorite', false)
        ->assertJsonPath('data.1.type', 'episode')
        ->assertJsonPath('data.2.type', 'channel');

    // Ma liste and Favoris don't overwrite each other.
    push([change(['favorite' => ['value' => true, 'at' => '2026-06-01T11:00:00Z']])])
        ->assertJsonPath('data.0.is_favorite', true)
        ->assertJsonPath('data.0.is_liked', true);

    // An older "unlike" from an offline device loses; a newer one wins and keeps Ma liste.
    push([change(['liked' => ['value' => false, 'at' => '2026-06-01T09:00:00Z']])])->assertJsonPath('data.0.is_liked', true);
    push([change(['liked' => ['value' => false, 'at' => '2026-06-01T12:00:00Z']])])
        ->assertJsonPath('data.0.is_liked', false)
        ->assertJsonPath('data.0.liked_at', null)
        ->assertJsonPath('data.0.is_favorite', true);

    // A new device downloads the liked items.
    expect(pull()->json('data.*.item_id'))->toBe(['episode:1399:1:3', 'channel:tf1', 'movie:550']);
});

it('keeps the original date when liking again', function () {
    push([change(['liked' => ['value' => true, 'at' => '2026-06-01T08:00:00Z']])]);
    push([change(['liked' => ['value' => true, 'at' => '2026-06-01T09:00:00Z']])])
        ->assertJsonPath('data.0.liked_at', '2026-06-01T08:00:00.000000Z');
});

it('paginates pulls', function () {
    push(collect(range(1, 5))->map(fn (int $n) => change([
        'item_id' => "movie:{$n}", 'favorite' => ['value' => true, 'at' => '2026-06-01T10:00:00Z'],
    ]))->all());

    pull(0, 2)->assertJsonPath('has_more', true)->assertJsonPath('cursor', 2)->assertJsonCount(2, 'data');
    pull(2, 2)->assertJsonPath('has_more', true)->assertJsonPath('cursor', 4);
    pull(4, 2)->assertJsonPath('has_more', false)->assertJsonPath('cursor', 5)->assertJsonCount(1, 'data');
});

it('keeps profiles separate', function () {
    $other = Profile::factory()->for($this->user)->create();
    ProfileItem::factory()->for($other)->favorite()->create();

    pull()->assertJsonPath('data', []);
    $this->getJson("/api/v1/profiles/{$other->id}/sync")->assertJsonCount(1, 'data');
});

it('forbids syncing another account profile', function () {
    $foreign = Profile::factory()->create();

    $this->getJson("/api/v1/profiles/{$foreign->id}/sync")->assertForbidden();
    $this->postJson("/api/v1/profiles/{$foreign->id}/sync", ['changes' => [change(['watched' => ['value' => true, 'at' => '2026-06-01T10:00:00Z']])]])
        ->assertForbidden();
    $this->getJson("/api/v1/profiles/{$foreign->id}/favorites")->assertForbidden();
    $this->getJson("/api/v1/profiles/{$foreign->id}/continue-watching")->assertForbidden();
    $this->getJson("/api/v1/profiles/{$foreign->id}/history")->assertForbidden();

    expect(ProfileItem::count())->toBe(0);
});

it('deletes the items with the profile', function () {
    Profile::factory()->for($this->user)->create();
    ProfileItem::factory()->for($this->profile)->favorite()->create();

    $this->deleteJson("/api/v1/profiles/{$this->profile->id}")->assertNoContent();

    expect(ProfileItem::count())->toBe(0);
});

it('validates changes', function (array $changes, string $error) {
    push($changes)->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'no state' => [[change()], 'changes.0.favorite'],
    'unknown type' => [[change(['type' => 'book', 'watched' => ['value' => true, 'at' => '2026-06-01']])], 'changes.0.type'],
    'bad source' => [[change(['source' => 'tmdb/../x', 'watched' => ['value' => true, 'at' => '2026-06-01']])], 'changes.0.source'],
    'missing date' => [[change(['favorite' => ['value' => true]])], 'changes.0.favorite.at'],
    'missing liked date' => [[change(['liked' => ['value' => true]])], 'changes.0.liked.at'],
    'missing flag' => [[change(['progress' => ['position_ms' => 10, 'at' => '2026-06-01']])], 'changes.0.progress.continue_watching'],
    'credentials in image URL' => [[change([
        'poster_url' => 'https://jellyfin.local/Items/1/Images/Primary?api_key=secret',
        'watched' => ['value' => true, 'at' => '2026-06-01'],
    ])], 'changes.0.poster_url'],
    'not http' => [[change(['poster_url' => 'file:///etc/passwd', 'watched' => ['value' => true, 'at' => '2026-06-01']])], 'changes.0.poster_url'],
]);

it('limits the batch size', function () {
    push(array_fill(0, 501, change(['watched' => ['value' => true, 'at' => '2026-06-01']])))
        ->assertUnprocessable()->assertJsonValidationErrors('changes');
});

it('explains validation errors in French', function () {
    push([change(['poster_url' => 'https://x.test/a.jpg?token=abc', 'favorite' => ['value' => true]])])
        ->assertJsonPath('errors', [
            'changes.0.poster_url' => ["Le champ affiche ne doit pas contenir d'identifiants (clé API, jeton…)."],
            'changes.0.favorite.at' => ['Le champ date du favori est obligatoire quand favori est présent.'],
        ]);
});
