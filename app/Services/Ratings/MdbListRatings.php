<?php

namespace App\Services\Ratings;

use App\Enums\MediaType;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ratings of other sites for TMDb titles, from MDBList (docs/notes.md): IMDb, TMDb, Trakt,
 * Letterboxd, Rotten Tomatoes (critics and audience), Metacritic, MyAnimeList.
 *
 * Vixeo's key has a daily quota shared by every account (free plan: 1 000 requests), so:
 * - the ratings of a title are cached for every account (3 days; a day for a title MDBList does
 *   not know);
 * - titles missing from the cache are asked together, 200 per request (MDBList's batch);
 * - at most `daily_requests` requests a day (UTC); after a 429 or a refused key, none for an hour;
 *   after a network error or a 5xx, none for a minute.
 * Titles that could not be asked are left out and `available` is false: the apps show TMDb's
 * rating meanwhile and ask again later.
 */
class MdbListRatings
{
    /** Titles per MDBList request (its batch). */
    public const BATCH = 200;

    /** Sources given for each title, with the scale of their value. */
    public const SCALES = [
        'imdb' => 10,
        'tmdb' => 10,
        'trakt' => 100,
        'letterboxd' => 5,
        'tomatoes' => 100,
        'audience' => 100,
        'metacritic' => 100,
        'mal' => 10,
    ];

    private const PAUSE_KEY = 'mdblist:paused';

    /**
     * Ratings of these titles, keyed `movie:550`. Titles MDBList does not know have every
     * source at null; titles that could not be asked now are missing.
     *
     * @param  list<array{MediaType, int}>  $titles
     * @return array{ratings: array<string, array<string, int|float|null>>, available: bool}
     */
    public function for(array $titles): array
    {
        $keys = [];
        foreach ($titles as [$type, $id]) {
            $keys[self::key($type, $id)] = [$type, $id];
        }
        if ($keys === []) {
            return ['ratings' => [], 'available' => true];
        }

        $cache = $this->cache();
        $ratings = [];
        $missing = [];
        foreach ($cache->many(array_map(fn (string $key) => "mdblist:{$key}", array_keys($keys))) as $cacheKey => $value) {
            $key = substr((string) $cacheKey, strlen('mdblist:'));
            if (is_array($value)) {
                $ratings[$key] = $value;
            } else {
                $missing[] = $keys[$key];
            }
        }

        $available = true;
        foreach ([MediaType::Movie, MediaType::Tv] as $type) {
            $ids = array_values(array_map(fn (array $t) => $t[1], array_filter($missing, fn (array $t) => $t[0] === $type)));
            foreach (array_chunk($ids, self::BATCH) as $chunk) {
                $fetched = $this->fetch($type, $chunk);
                if ($fetched === null) {
                    $available = false;
                    break 2;
                }
                foreach ($chunk as $id) {
                    $key = self::key($type, $id);
                    $known = array_key_exists($id, $fetched);
                    $ratings[$key] = $known ? $fetched[$id] : self::none();
                    $ttl = $known && array_filter($ratings[$key], fn ($v) => $v !== null) !== []
                        ? (int) config('services.mdblist.ttl.ratings')
                        : (int) config('services.mdblist.ttl.unknown');
                    $cache->put("mdblist:{$key}", $ratings[$key], $ttl);
                }
            }
        }

        return ['ratings' => $ratings, 'available' => $available];
    }

    /**
     * One MDBList request: ratings by TMDb id, or null when MDBList cannot be asked now.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, int|float|null>>|null
     */
    private function fetch(MediaType $type, array $ids): ?array
    {
        $apiKey = (string) config('services.mdblist.api_key');
        $cache = $this->cache();
        if ($apiKey === '' || $cache->has(self::PAUSE_KEY) || ! $this->spend($cache)) {
            return null;
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.mdblist.base_url'), '/'))
                ->acceptJson()
                ->withUserAgent('Vixeo')
                // Short: the apps wait for the ratings before showing a title's facts.
                ->connectTimeout(2)
                ->timeout(5)
                // The key travels in the query string (MDBList's way): never log the URL.
                ->withQueryParameters(['apikey' => $apiKey])
                ->post($type === MediaType::Movie ? 'tmdb/movie' : 'tmdb/show', ['ids' => $ids]);
        } catch (ConnectionException) {
            Log::warning('MDBList unreachable', ['type' => $type->value]);
            $cache->put(self::PAUSE_KEY, true, 60);

            return null;
        }

        $json = $response->json();
        $limited = $response->status() === 429
            || (is_array($json) && ! array_is_list($json) && str_contains(strtolower((string) ($json['error'] ?? '')), 'limit'));
        if ($limited || in_array($response->status(), [401, 403], true)) {
            Log::warning('MDBList refused the request', ['type' => $type->value, 'status' => $response->status()]);
            $cache->put(self::PAUSE_KEY, true, 3600);

            return null;
        }
        if ($response->failed() || ! is_array($json) || ! array_is_list($json)) {
            Log::warning('MDBList request failed', ['type' => $type->value, 'status' => $response->status()]);
            $cache->put(self::PAUSE_KEY, true, 60);

            return null;
        }

        $result = [];
        foreach ($json as $item) {
            $id = is_array($item) ? self::tmdbId($item) : null;
            if ($id !== null && in_array($id, $ids, true)) {
                $result[$id] = self::parse($item);
            }
        }

        return $result;
    }

    /** Counts one request against today's budget; false when it is spent. */
    private function spend(Repository $cache): bool
    {
        $key = 'mdblist:requests:'.now('UTC')->toDateString();
        $cache->add($key, 0, 172800);
        if ((int) $cache->get($key, 0) >= (int) config('services.mdblist.daily_requests')) {
            return false;
        }
        $cache->increment($key);

        return true;
    }

    /** @param  array<string, mixed>  $item */
    private static function tmdbId(array $item): ?int
    {
        $ids = is_array($item['ids'] ?? null) ? $item['ids'] : [];
        foreach ([$item['tmdbid'] ?? null, $item['tmdb_id'] ?? null, $ids['tmdb'] ?? null, $item['id'] ?? null] as $value) {
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * The `ratings` of an MDBList title, each on its site's scale (IMDb, TMDb and MyAnimeList
     * out of 10, Letterboxd out of 5, the others out of 100); null when the site has none.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, int|float|null>
     */
    public static function parse(array $item): array
    {
        $values = self::none();
        foreach ((array) ($item['ratings'] ?? []) as $rating) {
            if (! is_array($rating)) {
                continue;
            }
            $source = match ($rating['source'] ?? null) {
                'imdb', 'tmdb', 'trakt', 'letterboxd', 'tomatoes', 'metacritic' => (string) $rating['source'],
                'popcorn', 'tomatoesaudience', 'audience' => 'audience',
                'myanimelist', 'mal' => 'mal',
                default => null,
            };
            if ($source !== null && $values[$source] === null) {
                $values[$source] = self::value($source, $rating);
            }
        }

        return $values;
    }

    /** @param  array<string, mixed>  $rating */
    private static function value(string $source, array $rating): int|float|null
    {
        $value = is_numeric($rating['value'] ?? null) ? (float) $rating['value'] : null;
        // `score` is MDBList's value out of 100, for every site.
        $score = is_numeric($rating['score'] ?? null) ? (float) $rating['score'] : null;
        if (is_numeric($rating['votes'] ?? null) && (int) $rating['votes'] === 0) {
            return null;
        }

        $scale = self::SCALES[$source];
        $result = match (true) {
            // Letterboxd's value is out of 10 in batch answers, out of 5 otherwise.
            $source === 'letterboxd' => $score !== null ? $score / 20 : ($value !== null && $value > 5 ? $value / 2 : $value),
            // TMDb's value is out of 100 on MDBList.
            $source === 'tmdb' => $score !== null ? $score / 10 : ($value !== null && $value > 10 ? $value / 10 : $value),
            $scale === 10 => $value ?? ($score !== null ? $score / 10 : null),
            default => $value ?? $score,
        };
        if ($result === null || ! is_finite($result) || $result <= 0 || $result > $scale) {
            return null;
        }

        return match ($scale) {
            100 => (int) round($result),
            default => round($result, $source === 'mal' ? 2 : 1),
        };
    }

    /** @return array<string, null> */
    private static function none(): array
    {
        return array_fill_keys(array_keys(self::SCALES), null);
    }

    private static function key(MediaType $type, int $id): string
    {
        return "{$type->value}:{$id}";
    }

    private function cache(): Repository
    {
        return Cache::store(config('services.mdblist.cache_store'));
    }
}
