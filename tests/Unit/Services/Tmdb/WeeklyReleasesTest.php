<?php

use App\Services\Tmdb\TitleSummaries;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbUnavailableException;
use App\Services\Tmdb\UpcomingEpisodes;
use App\Services\Tmdb\WeeklyReleases;
use Illuminate\Support\Facades\Cache;

it('serves the previous weekly result when TMDB is temporarily unavailable', function () {
    $from = '2026-11-23';
    $to = '2026-11-29';
    $stale = [
        ['type' => 'movie', 'tmdb_id' => 550, 'title' => 'Cached release'],
    ];

    Cache::shouldReceive('remember')
        ->once()
        ->andThrow(new TmdbUnavailableException);
    Cache::shouldReceive('get')
        ->once()
        ->andReturn($stale);

    $service = new WeeklyReleases(
        Mockery::mock(TmdbClient::class),
        Mockery::mock(UpcomingEpisodes::class),
        Mockery::mock(TitleSummaries::class),
    );

    expect($service->between($from, $to))->toBe($stale);
});
