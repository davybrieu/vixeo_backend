<?php

namespace App\Services\Tmdb;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache as CacheFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Thin, cached client for the TMDb v3 API.
 *
 * Successful responses are cached; errors never are. A TMDb 404 becomes our 404,
 * any other failure (network, 5xx, 429 after retries, missing credentials) a 503.
 */
class TmdbClient
{
    private Cache $cache;

    public function __construct()
    {
        $this->cache = CacheFactory::store(config('services.tmdb.cache_store'));
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = [], string $ttl = 'details'): array
    {
        $query = ['language' => config('services.tmdb.language')] + $query;
        ksort($query);

        $key = 'tmdb:'.sha1($path.'?'.http_build_query($query));

        return $this->cache->remember(
            $key,
            (int) config("services.tmdb.ttl.{$ttl}", 3600),
            fn () => $this->fetch($path, $query),
        );
    }

    /**
     * Several paths at once (cache first, missing ones fetched concurrently, no retry).
     * A path that fails or does not exist maps to null.
     *
     * @param  list<string>  $paths
     * @param  array<string, string>  $query  Extra parameters, the same for every path.
     * @return array<string, array<string, mixed>|null>
     */
    public function getMany(array $paths, string $ttl = 'details', int $concurrency = 10, array $query = []): array
    {
        $query = ['language' => config('services.tmdb.language')] + $query;
        ksort($query);
        $keys = [];
        $results = [];

        foreach (array_unique($paths) as $path) {
            $keys[$path] = 'tmdb:'.sha1($path.'?'.http_build_query($query));
            $results[$path] = $this->cache->get($keys[$path]);
        }

        $missing = array_keys(array_filter($results, fn ($value) => $value === null));

        if ($missing === []) {
            return $results;
        }

        $this->ensureConfigured();

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $path) => $this->configure($pool->as($path))->get(ltrim($path, '/'), $this->withApiKey($query)),
                $missing,
            ), $concurrency);
        } catch (ConnectionException) {
            $responses = [];
        }

        foreach ($missing as $path) {
            $response = $responses[$path] ?? null;

            if ($response instanceof Response && $response->successful()) {
                $results[$path] = $response->json() ?? [];
                $this->cache->put($keys[$path], $results[$path], (int) config("services.tmdb.ttl.{$ttl}", 3600));
            } else {
                Log::warning('TMDb batch request failed', [
                    'path' => $path,
                    'status' => $response instanceof Response ? $response->status() : null,
                ]);
            }
        }

        return $results;
    }

    /**
     * One uncached call, for a person's TMDb account (session_id in `$query`, as TMDb v3 wants).
     * The caller reads the status: nothing is thrown except when TMDb can't be reached. Only
     * the path is ever logged, never the query (it holds the session).
     *
     * @param  'get'|'post'|'delete'  $method
     * @param  array<string, scalar>  $query
     * @param  array<string, mixed>  $body
     */
    public function send(string $method, string $path, array $query = [], array $body = []): Response
    {
        $this->ensureConfigured();
        $query = $this->withApiKey(['language' => config('services.tmdb.language')] + $query);
        $url = ltrim($path, '/').'?'.http_build_query($query);
        $request = $this->configure(Http::createPendingRequest());

        try {
            return match ($method) {
                'get' => $request->retry([200, 600], when: fn (Throwable $e) => $e instanceof ConnectionException, throw: false)->get($url),
                'post' => $request->post($url, $body),
                'delete' => $request->delete($url, $body),
            };
        } catch (ConnectionException) {
            Log::warning('TMDb unreachable', ['path' => $path]);

            throw new TmdbUnavailableException;
        }
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function fetch(string $path, array $query): array
    {
        try {
            $response = $this->request()->get(ltrim($path, '/'), $this->withApiKey($query));
        } catch (ConnectionException $e) {
            Log::warning('TMDb unreachable', ['path' => $path, 'error' => $e->getMessage()]);

            throw new TmdbUnavailableException($e);
        }

        if ($response->notFound()) {
            throw new NotFoundHttpException;
        }

        if ($response->unauthorized()) {
            Log::error('TMDb rejected our credentials: check TMDB_READ_ACCESS_TOKEN / TMDB_API_KEY.', ['path' => $path]);

            throw new TmdbUnavailableException;
        }

        if ($response->failed()) {
            Log::warning('TMDb request failed', ['path' => $path, 'status' => $response->status()]);

            throw new TmdbUnavailableException;
        }

        return $response->json() ?? [];
    }

    private function request(): PendingRequest
    {
        $this->ensureConfigured();

        return $this->configure(Http::createPendingRequest())
            ->retry([200, 600], when: fn (Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->serverError() || $e->response->tooManyRequests())),
                throw: false);
    }

    private function configure(PendingRequest $request): PendingRequest
    {
        $token = config('services.tmdb.token');

        return $request->baseUrl(rtrim(config('services.tmdb.base_url'), '/'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->when($token, fn (PendingRequest $request) => $request->withToken($token));
    }

    private function ensureConfigured(): void
    {
        if (! config('services.tmdb.token') && ! config('services.tmdb.api_key')) {
            Log::error('TMDb is not configured: set TMDB_READ_ACCESS_TOKEN or TMDB_API_KEY.');

            throw new TmdbUnavailableException;
        }
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, scalar>
     */
    private function withApiKey(array $query): array
    {
        if (! config('services.tmdb.token')) {
            $query['api_key'] = config('services.tmdb.api_key');
        }

        return $query;
    }
}
