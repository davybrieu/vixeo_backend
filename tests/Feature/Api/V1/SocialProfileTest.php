<?php

use App\Models\DiaryEntry;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\TmdbRating;
use App\Models\User;
use App\Support\Usernames;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['services.tmdb.image_base_url' => 'https://image.tmdb.org/t/p']);
    $this->member = Profile::factory()->for(User::factory())->create(['username' => 'lea']);
    Sanctum::actingAs(User::factory()->create());
});

it('lists a member\'s reviews with the fields of « Mes avis », images included', function () {
    TmdbRating::forceCreate([
        'profile_id' => $this->member->id, 'target' => 'movie/550', 'type' => 'movie',
        'tmdb_id' => 550, 'value' => 8.5, 'comment' => 'Une claque.', 'title' => 'Fight Club',
        'date' => '1999-10-15', 'poster_path' => '/fc.jpg', 'rated_at' => now()->subDay(),
    ]);
    TmdbRating::forceCreate([
        'profile_id' => $this->member->id, 'target' => 'tv/1399/season/1/episode/2',
        'type' => 'episode', 'tmdb_id' => 1399, 'season_number' => 1, 'episode_number' => 2, 'value' => 7,
        'title' => 'Game of Thrones', 'episode_name' => 'La route royale', 'still_path' => '/ep.jpg',
        'rated_at' => now(),
    ]);

    $this->getJson('/api/v1/social/profiles/lea/reviews')
        ->assertOk()
        // The most recent first.
        ->assertJsonPath('data.0.type', 'episode')
        ->assertJsonPath('data.0.id', 1399)
        ->assertJsonPath('data.0.season_number', 1)
        ->assertJsonPath('data.0.episode_number', 2)
        ->assertJsonPath('data.0.episode_name', 'La route royale')
        ->assertJsonPath('data.0.still_url', 'https://image.tmdb.org/t/p/w300/ep.jpg')
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
        ->assertJsonPath('data.bio', 'Fan de science-fiction.')
        // Its page on the site, shared from the app.
        ->assertJsonPath('data.share_url', 'https://app.vixeo.test/membre/lea');

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

it('lists a member\'s « Favoris » ❤ with its counts', function () {
    $item = fn (array $attributes) => ProfileItem::forceCreate($attributes + [
        'profile_id' => $this->member->id, 'source' => 'tmdb', 'revision' => 1,
    ]);
    $item(['item_id' => 'movie:550', 'type' => 'movie', 'title' => 'Fight Club', 'year' => 1999,
        'poster_url' => 'https://image.tmdb.org/t/p/w342/fc.jpg', 'is_liked' => true, 'liked_at' => now()->subDay()]);
    $item(['item_id' => 'tv:1399', 'type' => 'tv', 'title' => 'Game of Thrones', 'is_liked' => true, 'liked_at' => now()]);
    // In « Ma liste » only: not a favorite.
    $item(['item_id' => 'movie:13', 'type' => 'movie', 'title' => 'Forrest Gump', 'is_favorite' => true, 'favorited_at' => now()]);
    $this->getJson('/api/v1/social/profiles/lea/liked')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['Game of Thrones', 'Fight Club'])
        ->assertJsonPath('data.1.item_id', 'movie:550')
        ->assertJsonPath('data.1.poster_url', 'https://image.tmdb.org/t/p/w342/fc.jpg');

    $this->getJson('/api/v1/social/profiles/lea')
        ->assertOk()
        ->assertJsonPath('data.liked_count', 2)
        ->assertJsonPath('data.list_count', 1);
});

it('gives the time watched and the episodes watched of a profile', function () {
    $entry = fn (array $fields) => DiaryEntry::forceCreate([
        'profile_id' => $this->member->id, 'title' => 'Titre', 'watched_at' => now(), ...$fields,
    ]);
    $entry(['media_type' => 'movie', 'tmdb_id' => 550, 'runtime' => 139]);
    // Seen again: watched twice.
    $entry(['media_type' => 'movie', 'tmdb_id' => 550, 'runtime' => 139, 'rewatch' => true]);
    $entry(['media_type' => 'tv', 'tmdb_id' => 1399, 'season_number' => 1, 'episode_number' => 1, 'runtime' => 60]);
    // A whole TV show marked watched, and a movie whose length TMDb does not know: no time.
    $entry(['media_type' => 'tv', 'tmdb_id' => 1399, 'runtime' => 60]);
    $entry(['media_type' => 'movie', 'tmdb_id' => 13]);
    // Another profile's diary.
    DiaryEntry::forceCreate([
        'profile_id' => Profile::factory()->for(User::factory())->create()->id,
        'media_type' => 'movie', 'tmdb_id' => 550, 'title' => 'Fight Club', 'watched_at' => now(), 'runtime' => 139,
    ]);
    foreach (['tv:1399:1:1', 'tv:1399:1:2'] as $id) {
        ProfileItem::forceCreate([
            'profile_id' => $this->member->id, 'source' => 'tmdb', 'item_id' => $id, 'type' => 'episode',
            'title' => 'Épisode', 'is_watched' => true, 'watched_at' => now(), 'revision' => 1,
        ]);
    }

    $this->getJson('/api/v1/social/profiles/lea')
        ->assertOk()
        ->assertJsonPath('data.watch_minutes', 338)
        ->assertJsonPath('data.episodes_count', 2)
        ->assertJsonPath('data.watched_count', 0);
});

it('never gives a profile a username that is a page of the site or of the app', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['username' => 'maxime']);
    Sanctum::actingAs($user);

    // vixeo.app/membre/pseudo (Flutter Web): /accueil, /recherche… are app routes.
    foreach (['apk', 'Accueil', 'recherche', 'confidentialite'] as $reserved) {
        $this->withHeader('X-Profile-Id', (string) $profile->id)
            ->putJson("/api/v1/profiles/{$profile->id}", ['username' => $reserved])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'Ce pseudo est réservé : choisissez-en un autre.']);
    }
    $this->withHeader('X-Profile-Id', (string) $profile->id)
        ->putJson("/api/v1/profiles/{$profile->id}", ['username' => 'accueil_2'])
        ->assertOk()
        ->assertJsonPath('data.username', 'accueil_2');

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Paul', 'username' => 'faq', 'email' => 'paul@example.com',
        'password' => 'motdepasse-solide-42', 'device_name' => 'Pixel',
    ])->assertUnprocessable()->assertJsonValidationErrors('username');

    // Every page of the site with a one-word address is reserved; every reserved word could be one.
    $pages = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'site.'))
        ->map(fn ($route) => $route->uri())
        ->filter(fn (string $uri) => preg_match('/^'.Usernames::PATTERN.'$/', $uri) === 1);
    expect($pages->diff(Usernames::RESERVED)->all())->toBe([])
        ->and(Usernames::RESERVED)->each->toMatch('/^[a-z0-9_]{3,30}$/');
});
