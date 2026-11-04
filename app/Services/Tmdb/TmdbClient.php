<?php

namespace App\Services\Tmdb;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
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
        $token = config('services.tmdb.token');

        if (! $token && ! config('services.tmdb.api_key')) {
            Log::error('TMDb is not configured: set TMDB_READ_ACCESS_TOKEN or TMDB_API_KEY.');

            throw new TmdbUnavailableException;
        }

        return Http::baseUrl(rtrim(config('services.tmdb.base_url'), '/'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->when($token, fn (PendingRequest $request) => $request->withToken($token))
            ->retry([200, 600], when: fn (Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->serverError() || $e->response->tooManyRequests())),
                throw: false);
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
