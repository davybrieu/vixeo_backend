<?php

use App\Models\Profile;
use App\Models\TitleArtwork;
use App\Models\User;
use App\Services\AccountExport;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

beforeEach(function () {
    config(['services.tmdb.token' => 'test-token', 'services.tmdb.base_url' => 'https://api.themoviedb.org/3']);
    Http::preventStrayRequests();
    $images = ['images' => [
        'posters' => [
            ['file_path' => '/en.jpg', 'iso_639_1' => 'en', 'aspect_ratio' => 0.667, 'vote_average' => 6],
            ['file_path' => '/fr.jpg', 'iso_639_1' => 'fr', 'aspect_ratio' => 0.667, 'vote_average' => 5],
            ['file_path' => '/de.jpg', 'iso_639_1' => 'de', 'aspect_ratio' => 0.667, 'vote_average' => 9],
            ['file_path' => '/textless.jpg', 'iso_639_1' => null, 'aspect_ratio' => 0.667, 'vote_average' => 7],
        ],
        'backdrops' => [
            ['file_path' => '/b-en.jpg', 'iso_639_1' => 'en', 'aspect_ratio' => 1.778, 'vote_average' => 8],
            ['file_path' => '/b-plain.jpg', 'iso_639_1' => null, 'aspect_ratio' => 1.778, 'vote_average' => 4],
        ],
        'logos' => [
            ['file_path' => '/l-en.png', 'iso_639_1' => 'en', 'aspect_ratio' => 4.2, 'vote_average' => 5],
            ['file_path' => '/l-fr.png', 'iso_639_1' => 'fr', 'aspect_ratio' => 2.5, 'vote_average' => 3],
            ['file_path' => '/l-fr.svg', 'iso_639_1' => 'fr', 'aspect_ratio' => 2.5, 'vote_average' => 9],
        ],
    ]];
    Http::fake([
        'api.themoviedb.org/3/movie/550?*' => Http::response(Tmdb::movieDetails() + $images),
        'api.themoviedb.org/3/movie/*/release_dates*' => Http::response(['results' => []]),
    ]);

    $this->user = User::factory()->create();
    $this->profile = Profile::factory()->for($this->user)->create();
    $this->other = Profile::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user);
    $this->as = fn (Profile $profile) => $this->withHeader('X-Profile-Id', (string) $profile->id);
});

it('lists the images of a title: French, English and without text, the best first, and TMDb’s choice', function () {
    ($this->as)($this->profile)->getJson('/api/v1/catalog/movie/550/images')
        ->assertOk()
        ->assertJsonPath('data.default.poster', '/poster.jpg')
        ->assertJsonPath('data.default.logo', '/l-fr.png')
        ->assertJsonPath('data.chosen', ['poster' => null, 'backdrop' => null, 'logo' => null, 'logo_aspect_ratio' => null])
        ->assertJsonPath('data.posters.*.path', ['/fr.jpg', '/en.jpg', '/textless.jpg'])
        ->assertJsonPath('data.posters.0.url', 'https://image.tmdb.org/t/p/w342/fr.jpg')
        // Backdrops without text first.
        ->assertJsonPath('data.backdrops.*.path', ['/b-plain.jpg', '/b-en.jpg'])
        ->assertJsonPath('data.logos.*.path', ['/l-fr.png', '/l-en.png'])
        ->assertJsonPath('data.logos.1.aspect_ratio', 4.2);
});

it('gives the chosen poster, backdrop and logo to this profile only, everywhere', function () {
    $url = "/api/v1/profiles/{$this->profile->id}/artwork/movie/550";
    $this->putJson($url, ['poster' => '/en.jpg'])->assertOk()->assertJsonPath('data.poster', '/en.jpg');
    $this->putJson($url, ['backdrop' => '/b-en.jpg', 'logo' => '/l-en.png'])->assertOk()
        ->assertJsonPath('data', ['poster' => '/en.jpg', 'backdrop' => '/b-en.jpg', 'logo' => '/l-en.png', 'logo_aspect_ratio' => 4.2]);

    // The title page…
    ($this->as)($this->profile)->getJson('/api/v1/catalog/movie/550')->assertOk()
        ->assertJsonPath('data.poster_url', 'https://image.tmdb.org/t/p/w500/en.jpg')
        ->assertJsonPath('data.backdrop_url', 'https://image.tmdb.org/t/p/w1280/b-en.jpg')
        // The backdrop under the logo is the chosen one too.
        ->assertJsonPath('data.textless_backdrop_url', 'https://image.tmdb.org/t/p/w1280/b-en.jpg')
        ->assertJsonPath('data.logo_url', 'https://image.tmdb.org/t/p/w500/l-en.png')
        ->assertJsonPath('data.logo_aspect_ratio', 4.2);
    // … the lists…
    ($this->as)($this->profile)->getJson('/api/v1/catalog/titles?ids=movie:550')->assertOk()
        ->assertJsonPath('data.0.poster_url', 'https://image.tmdb.org/t/p/w342/en.jpg')
        ->assertJsonPath('data.0.logo_url', 'https://image.tmdb.org/t/p/w500/l-en.png');
    // … and the choice page.
    ($this->as)($this->profile)->getJson('/api/v1/catalog/movie/550/images')
        ->assertJsonPath('data.chosen.poster', '/en.jpg');

    // Another profile keeps TMDb's.
    ($this->as)($this->other)->getJson('/api/v1/catalog/movie/550')
        ->assertJsonPath('data.poster_url', 'https://image.tmdb.org/t/p/w500/poster.jpg')
        ->assertJsonPath('data.logo_url', 'https://image.tmdb.org/t/p/w500/l-fr.png');
    ($this->as)($this->other)->getJson('/api/v1/catalog/movie/550/images')
        ->assertJsonPath('data.chosen.poster', null);

    // Back to TMDb's, kind by kind; nothing left: nothing kept.
    $this->putJson($url, ['poster' => null])->assertJsonPath('data.poster', null)->assertJsonPath('data.logo', '/l-en.png');
    $this->putJson($url, ['backdrop' => null, 'logo' => null])
        ->assertJsonPath('data', ['poster' => null, 'backdrop' => null, 'logo' => null, 'logo_aspect_ratio' => null]);
    expect(TitleArtwork::count())->toBe(0);
});

it('refuses an image that is not one of the title’s, and another account’s profile', function () {
    $url = "/api/v1/profiles/{$this->profile->id}/artwork/movie/550";
    $this->putJson($url, ['poster' => '/de.jpg'])->assertUnprocessable()
        ->assertJsonPath('errors.poster.0', 'Cette image n’est pas une image de ce titre.');
    $this->putJson($url, ['logo' => '/l-fr.svg'])->assertJsonValidationErrors('logo');
    $this->putJson($url, ['poster' => 'https://evil.example/x.jpg'])->assertJsonValidationErrors('poster');

    Sanctum::actingAs(User::factory()->create());
    $this->putJson($url, ['poster' => '/en.jpg'])->assertForbidden();
    expect(TitleArtwork::count())->toBe(0);
});

it('keeps the choices in the export, and forgets them with the profile', function () {
    $this->putJson("/api/v1/profiles/{$this->profile->id}/artwork/movie/550", ['poster' => '/fr.jpg'])->assertOk();

    $export = app(AccountExport::class)->build($this->user);
    $profile = collect($export['profiles'])->firstWhere('name', $this->profile->name);
    expect($profile['title_images'])->toBe([['item_id' => 'movie:550', 'poster' => '/fr.jpg', 'backdrop' => null, 'logo' => null]]);

    $this->profile->delete();
    expect(TitleArtwork::count())->toBe(0);
});
