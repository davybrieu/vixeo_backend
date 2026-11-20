<?php

use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\TmdbRating;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['services.tmdb.image_base_url' => 'https://image.tmdb.org/t/p']);
    $this->member = Profile::factory()->for(User::factory())->create(['username' => 'lea']);
});

it('lists a member\'s reviews with the fields of « Mes avis », images included', function () {
    TmdbRating::forceCreate([
        'profile_id' => $this->member->id, 'target' => 'movie/550', 'type' => 'movie',
        'tmdb_id' => 550, 'value' => 8.5, 'comment' => 'Une claque.', 'title' => 'Fight Club',
        'date' => '1999-10-15', 'poster_path' => '/fc.jpg', 'rated_at' => now()->subDay(),
    ]);
    TmdbRating::forceCreate([
        'profile_id' => $this->member->id, 'target' => 'tv/1399', 'type' => 'tv', 'tmdb_id' => 1399,
        'value' => 7, 'title' => 'Game of Thrones', 'date' => '2011-04-17', 'rated_at' => now(),
    ]);

    $this->getJson('/api/v1/social/profiles/lea/reviews')
        ->assertOk()
        // The most recent first.
        ->assertJsonPath('data.0.type', 'tv')
        ->assertJsonPath('data.0.id', 1399)
        ->assertJsonPath('data.0.title', 'Game of Thrones')
        ->assertJsonPath('data.1.title', 'Fight Club')
        ->assertJsonPath('data.1.year', 1999)
        ->assertJsonPath('data.1.value', 8.5)
        ->assertJsonPath('data.1.comment', 'Une claque.')
        ->assertJsonPath('data.1.poster_url', 'https://image.tmdb.org/t/p/w342/fc.jpg')
        // Still there for the apps that read it.
        ->assertJsonPath('data.1.poster_path', '/fc.jpg');
});

it('shows a member\'s public page, with the avatar to draw it', function () {
    $this->member->update(['bio' => 'Fan de science-fiction.']);

    $this->getJson('/api/v1/social/profiles/lea')
        ->assertOk()
        ->assertJsonPath('data.username', 'lea')
        ->assertJsonPath('data.name', $this->member->name)
        ->assertJsonPath('data.avatar', $this->member->avatar)
        ->assertJsonPath('data.bio', 'Fan de science-fiction.');

    $this->getJson('/api/v1/social/search?query=le')
        ->assertOk()
        ->assertJsonPath('data.0.username', 'lea')
        ->assertJsonPath('data.0.avatar', $this->member->avatar);
});

it('never finds the active profile itself in the search', function () {
    $user = User::factory()->create();
    $me = Profile::factory()->for($user)->create(['username' => 'leane']);
    $mySecondProfile = Profile::factory()->for($user)->create(['username' => 'leon']);
    Sanctum::actingAs($user);

    $found = fn () => array_column($this->withHeader('X-Profile-Id', (string) $me->id)
        ->getJson('/api/v1/social/search?query=le')->assertOk()->json('data'), 'username');

    // Reached from the navigation (« Profil »), not from the search.
    expect($found())->not->toContain('leane')->toContain('lea', 'leon');
});

it('lists a member\'s « Favoris » ❤ and the people they follow, with their counts', function () {
    $item = fn (array $attributes) => ProfileItem::forceCreate($attributes + [
        'profile_id' => $this->member->id, 'source' => 'tmdb', 'revision' => 1,
    ]);
    $item(['item_id' => 'movie:550', 'type' => 'movie', 'title' => 'Fight Club', 'year' => 1999,
        'poster_url' => 'https://image.tmdb.org/t/p/w342/fc.jpg', 'is_liked' => true, 'liked_at' => now()->subDay()]);
    $item(['item_id' => 'tv:1399', 'type' => 'tv', 'title' => 'Game of Thrones', 'is_liked' => true, 'liked_at' => now()]);
    // In « Ma liste » only: not a favorite.
    $item(['item_id' => 'movie:13', 'type' => 'movie', 'title' => 'Forrest Gump', 'is_favorite' => true, 'favorited_at' => now()]);
    $item(['item_id' => 'person:287', 'type' => 'person', 'title' => 'Brad Pitt',
        'poster_url' => 'https://image.tmdb.org/t/p/w185/brad.jpg', 'is_followed' => true, 'followed_at' => now()]);

    $this->getJson('/api/v1/social/profiles/lea/liked')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['Game of Thrones', 'Fight Club'])
        ->assertJsonPath('data.1.item_id', 'movie:550')
        ->assertJsonPath('data.1.poster_url', 'https://image.tmdb.org/t/p/w342/fc.jpg');

    $this->getJson('/api/v1/social/profiles/lea/people')
        ->assertOk()
        ->assertExactJson(['data' => [[
            'id' => 287, 'item_id' => 'person:287', 'name' => 'Brad Pitt',
            'profile_url' => 'https://image.tmdb.org/t/p/w185/brad.jpg',
        ]], 'meta' => ['current_page' => 1, 'last_page' => 1]]);

    $this->getJson('/api/v1/social/profiles/lea')
        ->assertOk()
        ->assertJsonPath('data.liked_count', 2)
        ->assertJsonPath('data.people_count', 1)
        ->assertJsonPath('data.list_count', 1);
});
