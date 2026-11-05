<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

beforeEach(function () {
    config([
        'services.tmdb.token' => 'test-token',
        'services.tmdb.api_key' => null,
        'services.tmdb.base_url' => 'https://api.themoviedb.org/3',
    ]);
    Http::preventStrayRequests();
    Sanctum::actingAs(User::factory()->create());
});

function image(string $path, ?string $language, float $votes = 5.0, float $ratio = 1.778): array
{
    return ['file_path' => $path, 'iso_639_1' => $language, 'vote_average' => $votes, 'aspect_ratio' => $ratio];
}

it('gives the French title logo and a textless backdrop of a movie', function () {
    Http::fake(['api.themoviedb.org/3/movie/550*' => Http::response(array_merge(Tmdb::movieDetails(), [
        'images' => [
            'logos' => [
                image('/en.png', 'en', 9.0, 4.2),
                image('/fr-svg.svg', 'fr', 10.0),
                image('/fr-low.png', 'fr', 3.0, 2.5),
                image('/fr-best.png', 'fr', 6.0, 3.12),
            ],
            'backdrops' => [
                image('/with-text-fr.jpg', 'fr', 9.0),
                image('/textless.jpg', null, 5.6),
                image('/textless-low.jpg', null, 2.0),
            ],
        ],
    ]))]);

    $movie = $this->getJson('/api/v1/catalog/movie/550')->assertOk()->json('data');

    expect($movie['logo_path'])->toBe('/fr-best.png')
        ->and($movie['logo_url'])->toBe('https://image.tmdb.org/t/p/w500/fr-best.png')
        ->and($movie['logo_aspect_ratio'])->toBe(3.12)
        ->and($movie['textless_backdrop_path'])->toBe('/textless.jpg')
        ->and($movie['textless_backdrop_url'])->toBe('https://image.tmdb.org/t/p/w1280/textless.jpg')
        // The main backdrop is unchanged.
        ->and($movie['backdrop_path'])->toBe('/backdrop.jpg');

    Http::assertSent(fn (Request $request) => str_contains($request['append_to_response'], 'images')
        && $request['include_image_language'] === 'fr,en,null');
});

it('falls back to the English logo, and to nothing', function () {
    Http::fake([
        'api.themoviedb.org/3/tv/1399*' => Http::response(array_merge(Tmdb::showDetails(), [
            'images' => ['logos' => [image('/de.png', 'de', 10.0), image('/got.png', 'en', 5.0, 2.0)], 'backdrops' => []],
        ])),
        'api.themoviedb.org/3/movie/550*' => Http::response(array_merge(Tmdb::movieDetails(), [
            'images' => ['logos' => [image('/only.svg', 'fr')], 'backdrops' => [image('/fr.jpg', 'fr')]],
        ])),
    ]);

    $show = $this->getJson('/api/v1/catalog/tv/1399')->assertOk()->json('data');
    expect($show['logo_url'])->toBe('https://image.tmdb.org/t/p/w500/got.png')
        ->and($show['logo_aspect_ratio'])->toEqual(2.0)
        ->and($show['textless_backdrop_url'])->toBeNull();

    // Only an SVG logo, no textless backdrop: the app writes the title on the main backdrop.
    $movie = $this->getJson('/api/v1/catalog/movie/550')->assertOk()->json('data');
    expect($movie['logo_url'])->toBeNull()
        ->and($movie['logo_aspect_ratio'])->toBeNull()
        ->and($movie['textless_backdrop_url'])->toBeNull();
});

it('reads pages without images', function () {
    Http::fake(['api.themoviedb.org/3/movie/550*' => Http::response(Tmdb::movieDetails())]);

    $this->getJson('/api/v1/catalog/movie/550')
        ->assertOk()
        ->assertJsonPath('data.logo_url', null)
        ->assertJsonPath('data.textless_backdrop_url', null);
});

/** A typed home: every movie list answers with the same two titles. */
function fakeMovieHome(array $imagesById): void
{
    $fakes = [];
    foreach ($imagesById as $id => $images) {
        $fakes["api.themoviedb.org/3/movie/{$id}/images*"] = $images === null
            ? Http::response(['status_message' => 'Error'], 500)
            : Http::response($images);
    }
    Http::fake($fakes + [
        'api.themoviedb.org/3/trending/movie/week*' => Http::response(Tmdb::page([Tmdb::movie(1), Tmdb::movie(2)])),
        'api.themoviedb.org/3/movie/*' => Http::response(Tmdb::page([Tmdb::movie(1), Tmdb::movie(2)])),
    ]);
}

it('gives the title logo of each home item, fetched once per title and cached', function () {
    fakeMovieHome([
        1 => ['logos' => [image('/one.png', 'fr', 5.0, 4.0)], 'backdrops' => [], 'posters' => [
            image('/poster-fr.jpg', 'fr', 9.0, 0.667),
            image('/poster-clean.jpg', null, 5.2, 0.667),
        ]],
        2 => ['logos' => [], 'backdrops' => [], 'posters' => [image('/poster-en.jpg', 'en', 6.0, 0.667)]],
    ]);

    $home = $this->getJson('/api/v1/catalog/home?type=movie')->assertOk();

    expect($home->json('featured.0.logo_url'))->toBe('https://image.tmdb.org/t/p/w500/one.png')
        ->and($home->json('featured.0.logo_aspect_ratio'))->toEqual(4.0)
        // No logo on TMDb: the app writes the title.
        ->and($home->json('featured.1.logo_url'))->toBeNull()
        // A poster without text for the phone carousel; none for the second title (only an English one).
        ->and($home->json('featured.0.textless_poster_url'))->toBe('https://image.tmdb.org/t/p/w500/poster-clean.jpg')
        ->and($home->json('featured.0.textless_poster_path'))->toBe('/poster-clean.jpg')
        ->and($home->json('featured.1.textless_poster_url'))->toBeNull()
        ->and($home->json('sections.0.items.0.logo_url'))->toBe('https://image.tmdb.org/t/p/w500/one.png');

    $imageRequests = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), '/images'));
    // The same title in several rows: asked once, with the logo languages.
    expect($imageRequests())->toHaveCount(2)
        ->and($imageRequests()->every(fn ($pair) => $pair[0]['include_image_language'] === 'fr,en,null'))->toBeTrue();

    // Cached: the next home asks nothing more for the logos.
    $this->getJson('/api/v1/catalog/home?type=movie')->assertOk();
    expect($imageRequests())->toHaveCount(2);
});

it('keeps the home when a logo lookup fails', function () {
    fakeMovieHome([1 => null, 2 => ['logos' => [image('/two.png', 'en')], 'backdrops' => []]]);

    $this->getJson('/api/v1/catalog/home?type=movie')
        ->assertOk()
        ->assertJsonPath('featured.0.logo_url', null)
        ->assertJsonPath('featured.0.textless_poster_url', null)
        ->assertJsonPath('featured.1.logo_url', 'https://image.tmdb.org/t/p/w500/two.png');
});

it('gives no logo in search results (the title page has it)', function () {
    Http::fake([
        'api.themoviedb.org/3/search/*' => Http::response(Tmdb::page([Tmdb::movie(1)])),
    ]);

    $this->getJson('/api/v1/catalog/search?query=fight')
        ->assertOk()
        ->assertJsonPath('movies.data.0.logo_url', null)
        ->assertJsonPath('movies.data.0.textless_poster_url', null);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/images'));
});
