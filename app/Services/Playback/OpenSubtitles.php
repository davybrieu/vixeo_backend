<?php

namespace App\Services\Playback;

use App\Support\Languages;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Subtitles from OpenSubtitles.com, with Vixeo's own API key (and account, for a higher download
 * quota): apps never talk to OpenSubtitles. Search results are cached 6 hours; each file is
 * downloaded once, converted to WebVTT and cached 30 days for everyone, which saves the daily
 * download quota. See docs/lecture.md.
 */
class OpenSubtitles
{
    private const SEARCH_TTL = 21600;

    private const FILE_TTL = 2592000;

    private const MAX_RESULTS = 30;

    /**
     * Subtitles of a movie (season and episode null) or of a TV episode, in the given languages:
     * first language first, then the most downloaded.
     *
     * @param  list<string>  $languages  Codes of Languages::SUBTITLES.
     * @return list<array{file_id: int, language: string, release: string|null, hearing_impaired: bool, ai_translated: bool, machine_translated: bool, downloads: int}>
     */
    public function search(int $tmdbId, ?int $season, ?int $episode, array $languages): array
    {
        $languages = array_values(array_unique($languages));
        $sent = array_map(strtolower(...), $languages);
        sort($sent);

        $query = $season === null
            ? ['tmdb_id' => $tmdbId, 'type' => 'movie']
            : ['parent_tmdb_id' => $tmdbId, 'season_number' => $season, 'episode_number' => $episode, 'type' => 'episode'];
        $query['languages'] = implode(',', $sent);
        ksort($query);

        $results = Cache::remember('opensubtitles:search:'.sha1(http_build_query($query)), self::SEARCH_TTL, function () use ($query) {
            $response = $this->send(fn (PendingRequest $request) => $request->get('subtitles', $query));
            if ($response->failed()) {
                $this->fail($response, 'search');
            }

            return self::parse($response->json('data') ?? []);
        });

        $rank = array_flip(array_map(strtolower(...), $languages));
        usort($results, fn (array $a, array $b) => [$rank[strtolower($a['language'])] ?? 99, -$a['downloads']]
            <=> [$rank[strtolower($b['language'])] ?? 99, -$b['downloads']]);

        return array_slice($results, 0, self::MAX_RESULTS);
    }

    /** A subtitle file as WebVTT. */
    public function file(int $fileId): string
    {
        $key = "opensubtitles:file:{$fileId}";
        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }

        $token = $this->token();
        $response = $this->send(
            fn (PendingRequest $request) => $request
                ->when($token, fn (PendingRequest $r) => $r->withToken($token))
                ->post('download', ['file_id' => $fileId, 'sub_format' => 'srt']),
            $token === null ? null : Cache::get('opensubtitles:base_url'),
        );
        if ($response->status() === 404) {
            throw new NotFoundHttpException;
        }
        if ($response->failed() || ! is_string($response->json('link'))) {
            $this->fail($response, 'download');
        }

        try {
            $file = Http::connectTimeout(5)->timeout(20)->get($response->json('link'));
        } catch (ConnectionException $e) {
            throw new PlaybackServiceUnavailable('The subtitle service is temporarily unavailable.', $e);
        }
        if ($file->failed()) {
            $this->fail($file, 'file');
        }

        $vtt = SubtitleFormat::toWebVtt($file->body());
        Cache::put($key, $vtt, self::FILE_TTL);

        return $vtt;
    }

    /** Session of Vixeo's OpenSubtitles account (higher download quota), if configured. */
    private function token(): ?string
    {
        if (! config('services.opensubtitles.username') || ! config('services.opensubtitles.password')) {
            return null;
        }

        $cached = Cache::get('opensubtitles:token');
        if (is_string($cached)) {
            return $cached;
        }

        $response = $this->send(fn (PendingRequest $request) => $request->post('login', [
            'username' => config('services.opensubtitles.username'),
            'password' => config('services.opensubtitles.password'),
        ]));
        if ($response->failed() || ! is_string($response->json('token'))) {
            Log::error('OpenSubtitles login failed: check OPENSUBTITLES_USERNAME / OPENSUBTITLES_PASSWORD.', ['status' => $response->status()]);

            return null;
        }

        // Some accounts (VIP) are sent to another host.
        if (is_string($response->json('base_url')) && $response->json('base_url') !== '') {
            Cache::put('opensubtitles:base_url', 'https://'.preg_replace('#^https?://#', '', rtrim($response->json('base_url'), '/')).'/api/v1', 82800);
        }
        Cache::put('opensubtitles:token', $response->json('token'), 82800);

        return $response->json('token');
    }

    /** @param  callable(PendingRequest): Response  $call */
    private function send(callable $call, ?string $baseUrl = null): Response
    {
        $apiKey = config('services.opensubtitles.api_key');
        if (! $apiKey) {
            Log::error('OpenSubtitles is not configured: set OPENSUBTITLES_API_KEY.');

            throw new PlaybackServiceUnavailable('Subtitles are not available for now.');
        }

        try {
            return $call(Http::baseUrl($baseUrl ?? rtrim((string) config('services.opensubtitles.base_url'), '/'))
                ->acceptJson()
                ->withUserAgent((string) config('services.opensubtitles.user_agent'))
                ->withHeaders(['Api-Key' => $apiKey])
                ->connectTimeout(5)
                ->timeout(15));
        } catch (ConnectionException $e) {
            Log::warning('OpenSubtitles unreachable', ['error' => $e->getMessage()]);

            throw new PlaybackServiceUnavailable('The subtitle service is temporarily unavailable.', $e);
        }
    }

    private function fail(Response $response, string $step): never
    {
        Log::warning('OpenSubtitles request failed', ['step' => $step, 'status' => $response->status()]);

        if ($response->status() === 401 || $response->status() === 403) {
            Log::error('OpenSubtitles rejected our credentials: check OPENSUBTITLES_API_KEY.');
            Cache::forget('opensubtitles:token');
        }

        // 406: daily download quota used up.
        throw new PlaybackServiceUnavailable($response->status() === 406
            ? 'The daily subtitle download limit has been reached. Try again tomorrow.'
            : 'The subtitle service is temporarily unavailable.');
    }

    /**
     * @param  array<int, mixed>  $data
     * @return list<array{file_id: int, language: string, release: string|null, hearing_impaired: bool, ai_translated: bool, machine_translated: bool, downloads: int}>
     */
    private static function parse(array $data): array
    {
        $known = array_change_key_case(array_flip(Languages::SUBTITLES));
        $results = [];
        foreach ($data as $subtitle) {
            $attributes = is_array($subtitle['attributes'] ?? null) ? $subtitle['attributes'] : [];
            $language = is_string($attributes['language'] ?? null) ? $attributes['language'] : null;
            // Back to our codes ("pt-br" → "pt-BR").
            $code = $language === null ? null : (Languages::SUBTITLES[$known[strtolower($language)] ?? -1] ?? null);
            if ($code === null) {
                continue;
            }
            foreach ((array) ($attributes['files'] ?? []) as $file) {
                if (! is_array($file) || ! is_numeric($file['file_id'] ?? null)) {
                    continue;
                }
                $release = is_string($attributes['release'] ?? null) && $attributes['release'] !== '' ? $attributes['release'] : null;
                $results[] = [
                    'file_id' => (int) $file['file_id'],
                    'language' => $code,
                    'release' => $release,
                    'hearing_impaired' => (bool) ($attributes['hearing_impaired'] ?? false),
                    'ai_translated' => (bool) ($attributes['ai_translated'] ?? false),
                    'machine_translated' => (bool) ($attributes['machine_translated'] ?? false),
                    'downloads' => (int) ($attributes['download_count'] ?? 0),
                ];
            }
        }

        return $results;
    }
}
