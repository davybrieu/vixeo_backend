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
