<?php

use App\Enums\MediaType;
use App\Models\User;
use App\Services\Ratings\MdbListRatings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config([
        'services.mdblist.api_key' => 'mdb-key',
        'services.mdblist.base_url' => 'https://api.mdblist.com',
        'services.mdblist.daily_requests' => 900,
    ]);
    Http::preventStrayRequests();
    Sanctum::actingAs(User::factory()->create());
});

/** An MDBList title as its batch answers it. */
function mdbTitle(int $tmdbId, array $ratings): array
{
    return [
        'title' => "Title {$tmdbId}",
        'ids' => ['imdb' => "tt{$tmdbId}", 'tmdb' => $tmdbId, 'trakt' => 1, 'tvdb' => null],
        'ratings' => $ratings,
    ];
}

function fightClubRatings(): array
{
    return [
        ['source' => 'imdb', 'value' => 8.8, 'score' => 88, 'votes' => 2400000],
        ['source' => 'metacritic', 'value' => 67, 'score' => 67, 'votes' => 50],
        ['source' => 'metacriticuser', 'value' => 9.1, 'score' => 91, 'votes' => 4000],
        ['source' => 'trakt', 'value' => 88, 'score' => 88, 'votes' => 60000],
        ['source' => 'tomatoes', 'value' => 79, 'score' => 79, 'votes' => 150],
        ['source' => 'popcorn', 'value' => 96, 'score' => 96, 'votes' => 250000],
        ['source' => 'tmdb', 'value' => 84, 'score' => 84, 'votes' => 30000],
        // Out of 10 in batch answers: MDBList's score says 4,3 out of 5.
        ['source' => 'letterboxd', 'value' => 8.6, 'score' => 86, 'votes' => 900000],
        ['source' => 'rogerebert', 'value' => 2, 'score' => 50, 'votes' => 1],
        ['source' => 'myanimelist', 'value' => null, 'score' => null, 'votes' => 0],
    ];
}

it('gives the ratings of several titles, each on its site\'s scale, asked together by type', function () {
    Http::fake([
        'api.mdblist.com/tmdb/movie*' => Http::response([mdbTitle(550, fightClubRatings())]),
        'api.mdblist.com/tmdb/show*' => Http::response([
            mdbTitle(1399, [['source' => 'imdb', 'value' => 9.2, 'score' => 92, 'votes' => 2000000]]),
        ]),
    ]);

    $response = $this->getJson('/api/v1/catalog/ratings?ids=movie:550,tv:1399,movie:551')->assertOk();

    expect($response->json('available'))->toBeTrue()
        ->and($response->json('data'))->toHaveCount(3)
        ->and($response->json('data.0'))->toBe([
            'type' => 'movie',
            'id' => 550,
            'imdb' => 8.8,
            'tmdb' => 8.4,
            'trakt' => 88,
            'letterboxd' => 4.3,
            'tomatoes' => 79,
            'audience' => 96,
            'metacritic' => 67,
            'mal' => null,
        ])
        ->and($response->json('data.1.type'))->toBe('tv')
        ->and($response->json('data.1.imdb'))->toEqual(9.2)
        // Not known by MDBList: every rating at null.
        ->and($response->json('data.2.id'))->toBe(551)
        ->and($response->json('data.2.imdb'))->toBeNull();

    // One request per type, every id at once, Vixeo's key in the query string.
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_starts_with($request->url(), 'https://api.mdblist.com/tmdb/movie?')
        && str_contains($request->url(), 'apikey=mdb-key')
        && $request['ids'] === [550, 551]);
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.mdblist.com/tmdb/show?')
        && $request['ids'] === [1399]);
});

it('keeps the ratings for every account: MDBList is asked once', function () {
    Http::fake(['api.mdblist.com/tmdb/movie*' => Http::response([mdbTitle(550, fightClubRatings())])]);

    $this->getJson('/api/v1/catalog/ratings?ids=movie:550')->assertOk()->assertJsonPath('data.0.imdb', 8.8);
    $this->getJson('/api/v1/catalog/ratings?ids=movie:550')->assertOk()->assertJsonPath('data.0.imdb', 8.8);

    Http::assertSentCount(1);
});

it('asks MDBList 200 titles at a time', function () {
    Http::fake(['api.mdblist.com/tmdb/movie*' => Http::response([])]);

    $service = app(MdbListRatings::class);
    $result = $service->for(array_map(fn (int $id) => [MediaType::Movie, $id], range(1, 250)));

    expect($result['ratings'])->toHaveCount(250)->and($result['available'])->toBeTrue();
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => count($request['ids']) === MdbListRatings::BATCH);
    Http::assertSent(fn (Request $request) => count($request['ids']) === 50);
});

it('gives no rating without a key, and does not call MDBList', function () {
    config(['services.mdblist.api_key' => null]);

    $this->getJson('/api/v1/catalog/ratings?ids=movie:550')
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('data', []);

    Http::assertNothingSent();
});

it('pauses for an hour after MDBList\'s limit, without an error for the apps', function () {
    Log::spy();
    Http::fakeSequence('api.mdblist.com/*')
        ->push(['response' => false, 'error' => 'API Limit Reached!'], 429)
        ->push([mdbTitle(551, fightClubRatings())]);

    $this->getJson('/api/v1/catalog/ratings?ids=movie:550')
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('data', []);
    // Paused: not asked again.
    $this->getJson('/api/v1/catalog/ratings?ids=movie:551')->assertOk()->assertJsonPath('available', false);

    Http::assertSentCount(1);
    // The key is never written in the logs.
    Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => ! str_contains(json_encode($context), 'mdb-key'));

    $this->travel(61)->minutes();
    $this->getJson('/api/v1/catalog/ratings?ids=movie:551')->assertOk()->assertJsonPath('data.0.imdb', 8.8);
});

it('stops at the daily budget and gives what it keeps', function () {
    config(['services.mdblist.daily_requests' => 1]);
    Http::fake(['api.mdblist.com/tmdb/movie*' => Http::response([mdbTitle(550, fightClubRatings())])]);

    $this->getJson('/api/v1/catalog/ratings?ids=movie:550')->assertOk()->assertJsonPath('available', true);
    $this->getJson('/api/v1/catalog/ratings?ids=movie:550,movie:600')
        ->assertOk()
        ->assertJsonPath('available', false)
        // The kept title is still given.
        ->assertJsonPath('data.0.id', 550)
        ->assertJsonCount(1, 'data');

    Http::assertSentCount(1);
});

it('pauses a minute when MDBList fails', function () {
    Http::fake(['api.mdblist.com/*' => Http::response('oops', 502)]);

    $this->getJson('/api/v1/catalog/ratings?ids=movie:550')->assertOk()->assertJsonPath('available', false);
    $this->getJson('/api/v1/catalog/ratings?ids=movie:550')->assertOk()->assertJsonPath('available', false);
    Http::assertSentCount(1);

    $this->travel(2)->minutes();
    $this->getJson('/api/v1/catalog/ratings?ids=movie:550')->assertOk();
    Http::assertSentCount(2);
});

it('validates the titles asked', function () {
    $this->getJson('/api/v1/catalog/ratings')->assertUnprocessable()->assertJsonValidationErrors('ids');
    $this->getJson('/api/v1/catalog/ratings?ids=person:1')->assertUnprocessable()->assertJsonValidationErrors('ids');
    $ids = implode(',', array_map(fn (int $id) => "movie:{$id}", range(1, 101)));
    $this->getJson("/api/v1/catalog/ratings?ids={$ids}")->assertUnprocessable()->assertJsonValidationErrors('ids');
});

it('reads every shape of MDBList rating', function (array $rating, string $source, int|float|null $expected) {
    expect(MdbListRatings::parse(['ratings' => [$rating]])[$source])->toBe($expected);
})->with([
    'IMDb out of 10' => [['source' => 'imdb', 'value' => 7.45, 'score' => 75, 'votes' => 10], 'imdb', 7.5],
    'TMDb out of 100' => [['source' => 'tmdb', 'value' => 77, 'score' => null], 'tmdb', 7.7],
    'Letterboxd out of 5' => [['source' => 'letterboxd', 'value' => 3.8, 'score' => null], 'letterboxd', 3.8],
    'Letterboxd from its score' => [['source' => 'letterboxd', 'value' => 3.8, 'score' => 76], 'letterboxd', 3.8],
    'audience (old name)' => [['source' => 'tomatoesaudience', 'value' => 81], 'audience', 81],
    'MyAnimeList' => [['source' => 'myanimelist', 'value' => 8.62, 'score' => 86], 'mal', 8.62],
    'no vote yet' => [['source' => 'tomatoes', 'value' => 0, 'score' => 0, 'votes' => 0], 'tomatoes', null],
    'out of range' => [['source' => 'imdb', 'value' => 42], 'imdb', null],
    'not a number' => [['source' => 'metacritic', 'value' => 'tbd'], 'metacritic', null],
]);
