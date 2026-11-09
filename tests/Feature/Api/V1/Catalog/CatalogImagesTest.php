<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
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

/**
 * A typed home: every movie list answers with the same two titles; each title's details (with
 * its images) come from [$imagesById] (null: TMDb fails for that title).
 */
function fakeMovieHome(array $imagesById): void
{
    $fakes = [];
    foreach ($imagesById as $id => $images) {
        $fakes["api.themoviedb.org/3/movie/{$id}?*"] = $images === null
            ? Http::response(['status_message' => 'Error'], 500)
            : Http::response(array_merge(Tmdb::movieDetails(), ['id' => $id, 'images' => $images]));
    }
    Http::fake($fakes + [
        'api.themoviedb.org/3/trending/movie/week*' => Http::response(Tmdb::page([Tmdb::movie(1), Tmdb::movie(2)])),
        'api.themoviedb.org/3/movie/*' => Http::response(Tmdb::page([Tmdb::movie(1), Tmdb::movie(2)])),
    ]);
}

/** Requests for the details of titles (not the lists). */
function detailRequests(): Collection
{
    return collect(Http::recorded())->filter(fn ($pair) => preg_match('#/3/(movie|tv)/\d+\?#', $pair[0]->url()));
}

it('gives the logo, length, genres and age rating of each home item, fetched once per title and cached', function () {
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
        ->and($home->json('sections.0.items.0.logo_url'))->toBe('https://image.tmdb.org/t/p/w500/one.png')
        // What the hero shows, at once.
        ->and($home->json('featured.0.summary'))->toBe([
            'runtime' => 139,
            'number_of_seasons' => null,
            'genres' => ['Drame'],
            'age_rating' => 16,
        ])
        ->and($home->json('sections.0.items.1.summary.runtime'))->toBe(139);

    // The same title in several rows: asked once, with its images and certifications.
    expect(detailRequests())->toHaveCount(2)
        ->and(detailRequests()->every(fn ($pair) => $pair[0]['include_image_language'] === 'fr,en,null'
            && $pair[0]['append_to_response'] === 'images,release_dates'))->toBeTrue();

    // Cached: the next home asks nothing more.
    $this->getJson('/api/v1/catalog/home?type=movie')->assertOk();
    expect(detailRequests())->toHaveCount(2);
});

it('keeps the home when a title lookup fails', function () {
    fakeMovieHome([1 => null, 2 => ['logos' => [image('/two.png', 'en')], 'backdrops' => []]]);

    $this->getJson('/api/v1/catalog/home?type=movie')
        ->assertOk()
        ->assertJsonPath('featured.0.logo_url', null)
        ->assertJsonPath('featured.0.textless_poster_url', null)
        // The app then reads the title page.
        ->assertJsonPath('featured.0.summary', null)
        ->assertJsonPath('featured.1.logo_url', 'https://image.tmdb.org/t/p/w500/two.png')
        ->assertJsonPath('featured.1.summary.genres', ['Drame']);
});

it('gives the same at once in search results: seasons of a TV show', function () {
    Http::fake([
        'api.themoviedb.org/3/search/movie*' => Http::response(Tmdb::page([])),
        'api.themoviedb.org/3/search/person*' => Http::response(Tmdb::page([])),
        'api.themoviedb.org/3/search/tv*' => Http::response(Tmdb::page([Tmdb::show(1399)])),
        'api.themoviedb.org/3/tv/1399?*' => Http::response(array_merge(Tmdb::showDetails(), [
            'images' => ['logos' => [image('/got.png', 'en', 5.0, 2.0)], 'backdrops' => [], 'posters' => []],
        ])),
    ]);

    $this->getJson('/api/v1/catalog/search?query=game')
        ->assertOk()
        ->assertJsonPath('shows.data.0.logo_url', 'https://image.tmdb.org/t/p/w500/got.png')
        ->assertJsonPath('shows.data.0.summary.runtime', null)
        ->assertJsonPath('shows.data.0.summary.number_of_seasons', 8)
        ->assertJsonPath('shows.data.0.summary.genres', ['Science-Fiction & Fantastique']);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/3/tv/1399?')
        && $request['append_to_response'] === 'images,content_ratings');
});

it('gives no summary in other lists (the title page has it)', function () {
    Http::fake(['api.themoviedb.org/3/movie/popular*' => Http::response(Tmdb::page([Tmdb::movie(1)]))]);

    $this->getJson('/api/v1/catalog/movie/lists/popular')
        ->assertOk()
        ->assertJsonPath('data.0.logo_url', null)
        ->assertJsonPath('data.0.summary', null);
    expect(detailRequests())->toBeEmpty();
});

it('gives several titles at once with their logo and summary, in the order asked', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550?*' => Http::response(array_merge(Tmdb::movieDetails(), [
            'images' => ['logos' => [image('/fc.png', 'fr', 5.0, 3.0)], 'backdrops' => [], 'posters' => []],
        ])),
        'api.themoviedb.org/3/tv/1399?*' => Http::response(Tmdb::showDetails()),
        'api.themoviedb.org/3/movie/9?*' => Http::response(['status_code' => 34], 404),
    ]);

    $this->getJson('/api/v1/catalog/titles?ids=tv:1399,movie:550,movie:9,movie:550')->assertOk()
        // Unknown title left out, duplicates once.
        ->assertJsonPath('data.*.id', [1399, 550])
        ->assertJsonPath('data.0.type', 'tv')
        ->assertJsonPath('data.0.summary.number_of_seasons', 8)
        ->assertJsonPath('data.1.title', 'Fight Club')
        ->assertJsonPath('data.1.logo_url', 'https://image.tmdb.org/t/p/w500/fc.png')
        ->assertJsonPath('data.1.summary.runtime', 139)
        ->assertJsonPath('data.1.genre_ids', [18])
        ->assertJsonPath('data.1.year', 1999);

    // Cached with the home and search lists: asked again, nothing more is sent.
    $sent = count(Http::recorded());
    $this->getJson('/api/v1/catalog/titles?ids=movie:550')->assertOk();
    expect(Http::recorded())->toHaveCount($sent);

    $this->getJson('/api/v1/catalog/titles?ids=person:1')->assertUnprocessable();
    $this->getJson('/api/v1/catalog/titles?ids='.implode(',', array_map(fn ($i) => "movie:{$i}", range(1, 101))))->assertUnprocessable();
});

it('keeps only the titles a kid profile may see', function () {
    $user = User::factory()->create();
    $kid = Profile::factory()->for($user)->kid(12)->create();
    Sanctum::actingAs($user);
    Http::fake([
        'api.themoviedb.org/3/movie/550?*' => Http::response(Tmdb::movieDetails()),
        'api.themoviedb.org/3/movie/550/release_dates*' => Http::response(Tmdb::movieDetails()['release_dates']),
        'api.themoviedb.org/3/tv/1399?*' => Http::response(Tmdb::showDetails()),
        'api.themoviedb.org/3/tv/1399/content_ratings*' => Http::response(['results' => [['iso_3166_1' => 'FR', 'rating' => '10']]]),
    ]);

    $this->getJson('/api/v1/catalog/titles?ids=movie:550,tv:1399', ['X-Profile-Id' => $kid->id])->assertOk()
        ->assertJsonPath('data.*.id', [1399]);
});
