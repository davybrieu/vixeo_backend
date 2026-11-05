<?php

use App\Http\Middleware\ResolveProfile;
use App\Models\Profile;
use App\Models\User;
use App\Services\Playback\SubtitleFormat;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config([
        'services.theintrodb.api_key' => null,
        'services.theintrodb.base_url' => 'https://api.theintrodb.org/v4',
        'services.opensubtitles.api_key' => 'os-key',
        'services.opensubtitles.username' => null,
        'services.opensubtitles.password' => null,
        'services.opensubtitles.base_url' => 'https://api.opensubtitles.com/api/v1',
        'services.opensubtitles.user_agent' => 'Vixeo v1.0',
    ]);
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

// ─── TheIntroDB ───

it('gives the moments to skip in a movie, cached', function () {
    Http::fake(['api.theintrodb.org/v4/media*' => Http::response([
        'tvdb_id' => 169, 'type' => 'movie',
        'intro' => [['start_ms' => null, 'end_ms' => 23000]],
        'credits' => [['start_ms' => 5801777, 'end_ms' => 6371111], ['start_ms' => 6408000, 'end_ms' => null]],
        'recap' => [], 'preview' => [['start_ms' => null, 'end_ms' => null]],
    ])]);

    $this->getJson('/api/v1/catalog/movie/550/segments')
        ->assertOk()
        ->assertExactJson(['data' => [
            'intro' => [['start_ms' => null, 'end_ms' => 23000]],
            'recap' => [],
            'credits' => [['start_ms' => 5801777, 'end_ms' => 6371111], ['start_ms' => 6408000, 'end_ms' => null]],
            // A segment without any time is useless: left out.
            'preview' => [],
        ]]);
    $this->getJson('/api/v1/catalog/movie/550/segments')->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['tmdb_id'] == 550
        && ! isset($request->data()['season'])
        && ! $request->hasHeader('Authorization'));
});

it('asks TheIntroDB for an episode, with our key when there is one', function () {
    config(['services.theintrodb.api_key' => 'tidb-key']);
    Http::fake(['api.theintrodb.org/v4/media*' => Http::response(['type' => 'tv', 'intro' => [['start_ms' => 30000, 'end_ms' => 90000]]])]);

    $this->getJson('/api/v1/catalog/tv/1399/season/1/episode/2/segments')
        ->assertOk()
        ->assertJsonPath('data.intro.0.end_ms', 90000)
        ->assertJsonPath('data.credits', []);

    Http::assertSent(fn (Request $request) => $request['tmdb_id'] == 1399 && $request['season'] == 1 && $request['episode'] == 2
        && $request->hasHeader('Authorization', 'Bearer tidb-key'));
});

it('gives nothing to skip for a title nobody has timed, and a 503 when TheIntroDB fails', function () {
    Http::fake([
        'api.theintrodb.org/v4/media?tmdb_id=1*' => Http::response(['error' => 'Not found', 'code' => 'no_submissions'], 404),
        'api.theintrodb.org/v4/media?tmdb_id=2*' => Http::response(['error' => 'Boom'], 500),
    ]);

    $this->getJson('/api/v1/catalog/movie/1/segments')
        ->assertOk()
        ->assertExactJson(['data' => ['intro' => [], 'recap' => [], 'credits' => [], 'preview' => []]]);
    $this->getJson('/api/v1/catalog/movie/2/segments')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'Le service des génériques est momentanément indisponible.']);
});

// ─── OpenSubtitles ───

function openSubtitle(int $fileId, string $language, int $downloads, array $extra = []): array
{
    return ['id' => (string) $fileId, 'type' => 'subtitle', 'attributes' => array_merge([
        'language' => $language, 'download_count' => $downloads, 'hearing_impaired' => false,
        'ai_translated' => false, 'machine_translated' => false, 'release' => "Release {$fileId}",
        'files' => [['file_id' => $fileId, 'file_name' => "{$fileId}.srt"]],
    ], $extra)];
}

it('finds subtitles of a movie in the profile language, preferred language first', function () {
    Http::fake(['api.opensubtitles.com/api/v1/subtitles*' => Http::response(['data' => [
        openSubtitle(1, 'en', 9000),
        openSubtitle(2, 'pt-BR', 50),
        openSubtitle(3, 'pt-BR', 700, ['hearing_impaired' => true, 'machine_translated' => true, 'release' => '']),
        openSubtitle(4, 'xx', 10),
    ]])]);
    $profile = Profile::factory()->for($this->user)->create(['subtitle_language' => 'pt-BR']);

    $response = $this->getJson('/api/v1/catalog/movie/550/subtitles?languages=pt-BR,en', [ResolveProfile::HEADER => $profile->id])
        ->assertOk();

    expect($response->json('data.*.file_id'))->toBe([3, 2, 1])
        ->and($response->json('data.0'))->toBe([
            'file_id' => 3, 'language' => 'pt-BR', 'release' => null,
            'hearing_impaired' => true, 'ai_translated' => true, 'downloads' => 700,
        ]);

    // Lowercase, sorted languages; our key and app name.
    Http::assertSent(fn (Request $request) => $request['tmdb_id'] == 550 && $request['type'] === 'movie'
        && $request['languages'] === 'en,pt-br'
        && $request->hasHeader('Api-Key', 'os-key') && $request->hasHeader('User-Agent', 'Vixeo v1.0'));

    // Without languages: the profile's.
    $this->getJson('/api/v1/catalog/movie/551/subtitles', [ResolveProfile::HEADER => $profile->id])->assertOk();
    Http::assertSent(fn (Request $request) => $request['tmdb_id'] == 551 && $request['languages'] === 'pt-br');
});

it('finds subtitles of an episode, French by default', function () {
    Http::fake(['api.opensubtitles.com/api/v1/subtitles*' => Http::response(['data' => [openSubtitle(9, 'fr', 3)]])]);

    $this->getJson('/api/v1/catalog/tv/1399/season/1/episode/2/subtitles')->assertOk()->assertJsonPath('data.0.language', 'fr');

    Http::assertSent(fn (Request $request) => $request['parent_tmdb_id'] == 1399 && $request['season_number'] == 1
        && $request['episode_number'] == 2 && $request['type'] === 'episode' && $request['languages'] === 'fr');
});

it('refuses unknown subtitle languages', function () {
    $this->getJson('/api/v1/catalog/movie/550/subtitles?languages=fr,klingon')
        ->assertUnprocessable()
        ->assertJsonPath('errors.languages.0', "La valeur sélectionnée pour langues n'est pas valide.");
});

it('serves a subtitle file as WebVTT, downloaded once with our account', function () {
    config(['services.opensubtitles.username' => 'vixeo', 'services.opensubtitles.password' => 'secret']);
    Http::fake([
        'api.opensubtitles.com/api/v1/login' => Http::response(['token' => 'jwt', 'base_url' => 'api.opensubtitles.com']),
        'api.opensubtitles.com/api/v1/download' => Http::response(['link' => 'https://www.opensubtitles.com/download/abc/file.srt', 'remaining' => 99]),
        'www.opensubtitles.com/download/*' => Http::response("1\r\n00:00:01,000 --> 00:00:02,500\r\nBonjour\r\n"),
    ]);

    $response = $this->get('/api/v1/catalog/subtitles/123')->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('text/vtt; charset=utf-8')
        ->and($response->getContent())->toBe("WEBVTT\n\n1\n00:00:01.000 --> 00:00:02.500\nBonjour\n");

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/download')
        && $request['file_id'] === 123 && $request->hasHeader('Authorization', 'Bearer jwt')
        && $request->hasHeader('Api-Key', 'os-key'));

    // From our cache: OpenSubtitles is not asked again (login, download, file).
    $this->get('/api/v1/catalog/subtitles/123')->assertOk();
    Http::assertSentCount(3);
});

it('says when the daily subtitle quota is used up, or subtitles are not configured', function () {
    Http::fake(['api.opensubtitles.com/api/v1/download' => Http::response(['message' => 'You have downloaded your allowed 20 subtitles'], 406)]);

    $this->getJson('/api/v1/catalog/subtitles/123')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'La limite quotidienne de sous-titres est atteinte. Réessayez demain.']);

    config(['services.opensubtitles.api_key' => null]);
    $this->getJson('/api/v1/catalog/movie/550/subtitles')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'Les sous-titres ne sont pas disponibles pour le moment.']);
});

it('converts SRT files to WebVTT', function () {
    $latin1 = mb_convert_encoding("1\n00:00:01,000 --> 00:00:02,000\nÇa va très bien\n", 'Windows-1252', 'UTF-8');

    expect(SubtitleFormat::toWebVtt("\xEF\xBB\xBF".$latin1))->toBe("WEBVTT\n\n1\n00:00:01.000 --> 00:00:02.000\nÇa va très bien\n")
        // Commas elsewhere stay.
        ->and(SubtitleFormat::toWebVtt("1\n00:00:01,000 --> 00:00:02,000\nOui, 10,5 euros\n"))->toContain('Oui, 10,5 euros')
        // Already WebVTT: unchanged.
        ->and(SubtitleFormat::toWebVtt("WEBVTT\n\n00:01.000 --> 00:02.000\nHi\n"))->toBe("WEBVTT\n\n00:01.000 --> 00:02.000\nHi\n");
});
