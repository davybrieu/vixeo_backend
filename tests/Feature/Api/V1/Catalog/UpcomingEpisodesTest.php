<?php

use App\Models\Profile;
use App\Models\User;
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
    $this->travelTo(new DateTimeImmutable('2026-11-16 10:00:00', new DateTimeZone('UTC')));
});

/** TV show details as TMDb gives them (with certifications). */
function upcomingShow(int $id, array $overrides = []): array
{
    return Tmdb::show($id, array_merge([
        'networks' => [],
        'origin_country' => ['US'],
        'next_episode_to_air' => null,
        'content_ratings' => ['results' => []],
        'images' => ['logos' => [], 'backdrops' => [], 'posters' => []],
    ], $overrides));
}

function upcomingEpisode(int $season, int $number, string $date): array
{
    return [
        'id' => $season * 100 + $number,
        'season_number' => $season,
        'episode_number' => $number,
        'name' => "Épisode {$number}",
        'air_date' => $date,
        'still_path' => "/s{$season}e{$number}.jpg",
    ];
}

it('gives the coming episodes of the shows asked, the soonest first, never too early', function () {
    Http::fake([
        // A US channel (AMC): 21:00 in New York, the evening of the air date.
        'api.themoviedb.org/3/tv/1399?*' => Http::response(upcomingShow(1399, [
            'networks' => [['id' => 174, 'name' => 'AMC']],
            'next_episode_to_air' => upcomingEpisode(2, 3, '2026-11-17'),
        ])),
        'api.themoviedb.org/3/tv/1399/season/2*' => Http::response(['season_number' => 2, 'episodes' => [
            upcomingEpisode(2, 2, '2026-11-10'),
            upcomingEpisode(2, 3, '2026-11-17'),
            upcomingEpisode(2, 4, '2026-11-24'),
            // Beyond 60 days.
            upcomingEpisode(2, 5, '2027-02-01'),
        ]]),
        // Netflix: the whole season at once, 08:00 UTC.
        'api.themoviedb.org/3/tv/100088?*' => Http::response(upcomingShow(100088, [
            'name' => 'Lupin',
            'networks' => [['id' => 213, 'name' => 'Netflix']],
            'next_episode_to_air' => upcomingEpisode(1, 1, '2026-11-20'),
        ])),
        'api.themoviedb.org/3/tv/100088/season/1*' => Http::response(['season_number' => 1, 'episodes' => [
            upcomingEpisode(1, 1, '2026-11-20'),
            upcomingEpisode(1, 2, '2026-11-20'),
        ]]),
        // Ended: nothing to come.
        'api.themoviedb.org/3/tv/5?*' => Http::response(upcomingShow(5)),
        // A French channel, season unavailable: at least the next episode, 21:00 in Paris.
        'api.themoviedb.org/3/tv/6?*' => Http::response(upcomingShow(6, [
            'name' => 'Capitaine Marleau',
            'origin_country' => ['FR'],
            'next_episode_to_air' => upcomingEpisode(1, 1, '2026-11-18'),
        ])),
        'api.themoviedb.org/3/tv/6/season/1*' => Http::response([], 500),
        // Unknown.
        'api.themoviedb.org/3/tv/7?*' => Http::response(['status_code' => 34], 404),
    ]);

    $this->getJson('/api/v1/catalog/episodes/upcoming?ids=1399,100088,5,6,7,1399')->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.*.show_id', [1399, 6, 100088, 100088, 1399])
        ->assertJsonPath('data.*.episode_number', [3, 1, 1, 2, 4])
        ->assertJsonPath('data.*.available_at', [
            '2026-11-18T02:00:00+00:00',
            '2026-11-18T20:00:00+00:00',
            '2026-11-20T08:00:00+00:00',
            '2026-11-20T08:00:00+00:00',
            '2026-11-25T02:00:00+00:00',
        ])
        ->assertJsonPath('data.0.show_name', 'Game of Thrones')
        ->assertJsonPath('data.0.season_number', 2)
        ->assertJsonPath('data.0.name', 'Épisode 3')
        ->assertJsonPath('data.0.air_date', '2026-11-17')
        ->assertJsonPath('data.0.poster_url', 'https://image.tmdb.org/t/p/w342/got.jpg')
        ->assertJsonPath('data.0.still_url', 'https://image.tmdb.org/t/p/w300/s2e3.jpg')
        ->assertJsonPath('data.2.show_name', 'Lupin');

    // Cached (details shared with the home and Ma liste): asked again, nothing more is sent,
    // except the season TMDb could not give.
    $sent = count(Http::recorded());
    $this->getJson('/api/v1/catalog/episodes/upcoming?ids=1399,100088')->assertOk()->assertJsonCount(4, 'data');
    expect(Http::recorded())->toHaveCount($sent);
});

it('leaves out the shows a kid profile may not see', function () {
    $user = User::factory()->create();
    $kid = Profile::factory()->for($user)->kid(12)->create();
    Sanctum::actingAs($user);
    Http::fake([
        'api.themoviedb.org/3/tv/1?*' => Http::response(upcomingShow(1, [
            'next_episode_to_air' => upcomingEpisode(1, 1, '2026-11-20'),
            'content_ratings' => ['results' => [['iso_3166_1' => 'FR', 'rating' => '16']]],
        ])),
        'api.themoviedb.org/3/tv/2?*' => Http::response(upcomingShow(2, [
            'next_episode_to_air' => upcomingEpisode(1, 1, '2026-11-20'),
            'content_ratings' => ['results' => [['iso_3166_1' => 'FR', 'rating' => '10']]],
        ])),
        'api.themoviedb.org/3/tv/2/season/1*' => Http::response(['episodes' => [upcomingEpisode(1, 1, '2026-11-20')]]),
    ]);

    $this->getJson('/api/v1/catalog/episodes/upcoming?ids=1,2', ['X-Profile-Id' => $kid->id])->assertOk()
        ->assertJsonPath('data.*.show_id', [2]);
});

it('refuses ids that are not TV show ids', function (string $query) {
    $this->getJson("/api/v1/catalog/episodes/upcoming{$query}")->assertUnprocessable()->assertJsonValidationErrors('ids');
})->with([
    'missing' => '',
    'typed' => '?ids=tv:1399',
    'too many' => '?ids='.implode(',', range(1, 101)),
]);
