<?php

use App\Models\Profile;
use App\Models\TmdbRating;
use App\Models\User;

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
        ->assertJsonPath('data.bio', 'Fan de science-fiction.');

    $this->getJson('/api/v1/social/search?query=le')
        ->assertOk()
        ->assertJsonPath('data.0.username', 'lea')
        ->assertJsonPath('data.0.avatar', $this->member->avatar);
});
