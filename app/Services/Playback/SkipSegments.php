<?php

namespace App\Services\Playback;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Moments the player can skip (opening credits, recap, end credits, preview of the next episode),
 * from TheIntroDB (community timestamps, looked up by TMDb id). Cached a day; a title nobody has
 * timed yet is cached 6 hours with no segment. See docs/lecture.md.
 */
class SkipSegments
{
    public const TYPES = ['intro', 'recap', 'credits', 'preview'];

    private const TTL = 86400;

    private const EMPTY_TTL = 21600;

    /** @return array<string, list<array{start_ms: int|null, end_ms: int|null}>> */
    public function movie(int $tmdbId): array
    {
        return $this->lookup(['tmdb_id' => $tmdbId], "movie:{$tmdbId}");
    }

    /** @return array<string, list<array{start_ms: int|null, end_ms: int|null}>> */
    public function episode(int $tmdbId, int $season, int $episode): array
    {
        return $this->lookup(
            ['tmdb_id' => $tmdbId, 'season' => $season, 'episode' => $episode],
            "tv:{$tmdbId}:{$season}:{$episode}",
        );
    }

    /**
     * @param  array<string, int>  $query
     * @return array<string, list<array{start_ms: int|null, end_ms: int|null}>>
     */
    private function lookup(array $query, string $key): array
    {
        $cacheKey = "theintrodb:{$key}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $apiKey = config('services.theintrodb.api_key');
        try {
            $response = Http::baseUrl(rtrim((string) config('services.theintrodb.base_url'), '/'))
                ->acceptJson()
                ->withUserAgent('Vixeo')
                ->connectTimeout(3)
                ->timeout(6)
                ->when($apiKey, fn ($request) => $request->withToken($apiKey))
                ->retry([300], when: fn ($e) => $e instanceof ConnectionException, throw: false)
                ->get('media', $query);
        } catch (ConnectionException $e) {
            Log::warning('TheIntroDB unreachable', ['key' => $key, 'error' => $e->getMessage()]);

            throw new PlaybackServiceUnavailable('The skip service is temporarily unavailable.', $e);
        }

        // Nobody has timed this title yet: nothing to skip.
        if ($response->notFound()) {
            $segments = self::parse([]);
            Cache::put($cacheKey, $segments, self::EMPTY_TTL);

            return $segments;
        }

        if ($response->failed()) {
            Log::warning('TheIntroDB request failed', ['key' => $key, 'status' => $response->status()]);

            throw new PlaybackServiceUnavailable('The skip service is temporarily unavailable.');
        }

        $segments = self::parse($response->json() ?? []);
        Cache::put($cacheKey, $segments, self::TTL);

        return $segments;
    }

    /**
     * Times in milliseconds; a null start means "from the beginning", a null end "to the end".
     *
     * @param  array<string, mixed>  $json
     * @return array<string, list<array{start_ms: int|null, end_ms: int|null}>>
     */
    private static function parse(array $json): array
    {
        $result = [];
        foreach (self::TYPES as $type) {
            $result[$type] = [];
            foreach ((array) ($json[$type] ?? []) as $segment) {
                if (! is_array($segment)) {
                    continue;
                }
                $start = is_numeric($segment['start_ms'] ?? null) ? (int) $segment['start_ms'] : null;
                $end = is_numeric($segment['end_ms'] ?? null) ? (int) $segment['end_ms'] : null;
                if ($start === null && $end === null) {
                    continue;
                }
                $result[$type][] = ['start_ms' => $start, 'end_ms' => $end];
            }
        }

        return $result;
    }
}
