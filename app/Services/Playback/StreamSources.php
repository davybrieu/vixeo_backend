<?php

namespace App\Services\Playback;

use App\Models\MissingExtractor;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StreamSources
{
    /** Domaines supportés par les extracteurs du scraper Node. */
    private const SUPPORTED_DOMAINS = [
        'voe.sx', 'uqload.com', 'uqload.net', 'uqload.io', 'uqload.co',
        'streamtape.com', 'streamtape.net', 'streamtape.to', 'streamtape.xyz',
        'doodstream.com', 'dood.watch', 'dood.to', 'dood.so', 'dood.la', 'dood.li',
        'dood.ws', 'dood.pm', 'dood.re', 'dood.yt', 'doods.pro',
        'filemoon.sx', 'filemoon.to', 'filemoon.in', 'filemoon.nl',
        'streamwish.to', 'streamwish.com', 'awish.pro', 'embedwish.com',
        'kisswish.com', 'hlswish.com', 'swishsrv.com', 'playerwish.com',
        'mixdrop.co', 'mixdrop.to', 'mixdrop.ch', 'mixdrop.bz', 'mixdrop.ag',
    ];

    public function __construct(private TmdbClient $tmdb) {}

    public function movie(int $tmdbId): array
    {
        $meta = $this->tmdb->get("movie/{$tmdbId}");

        return $this->fetch([
            'type' => 'movie',
            'tmdb_id' => $tmdbId,
            'title' => $meta['title'] ?? '',
            'original_title' => $meta['original_title'] ?? ($meta['title'] ?? ''),
            'year' => isset($meta['release_date']) ? (int) substr($meta['release_date'], 0, 4) : null,
        ]);
    }

    public function episode(int $tmdbId, int $season, int $episode): array
    {
        $meta = $this->tmdb->get("tv/{$tmdbId}");

        return $this->fetch([
            'type' => 'tv',
            'tmdb_id' => $tmdbId,
            'title' => $meta['name'] ?? '',
            'original_title' => $meta['original_name'] ?? ($meta['name'] ?? ''),
            'year' => isset($meta['first_air_date']) ? (int) substr($meta['first_air_date'], 0, 4) : null,
            'season' => $season,
            'episode' => $episode,
        ]);
    }

    private function fetch(array $payload): array
    {
        $base = rtrim((string) config('services.scraper.base_url'), '/');
        if ($base === '') {
            Log::warning('STREAM scraper not configured (SCRAPER_URL)');

            return [];
        }

        try {
            $response = Http::baseUrl($base)
                ->acceptJson()
                ->timeout((int) config('services.scraper.timeout', 45))
                ->post('sources', $payload);
        } catch (\Throwable $e) {
            Log::warning('Scraper unreachable', ['error' => $e->getMessage()]);

            throw new PlaybackServiceUnavailable('Le service de sources est temporairement indisponible.', $e);
        }

        if ($response->failed()) {
            Log::warning('Scraper failed', ['status' => $response->status()]);

            throw new PlaybackServiceUnavailable('Le service de sources est temporairement indisponible.');
        }

        $sources = $response->json('sources') ?? [];
        $sources = is_array($sources) ? array_values($sources) : [];

        $this->recordMissingExtractors($sources, $payload);

        return $sources;
    }

    private function recordMissingExtractors(array $sources, array $payload): void
    {
        $now = now();
        $userId = Auth::id();

        foreach ($sources as $source) {
            $url = $source['url'] ?? '';
            if ($url === '' || $this->hasExtractor($url)) {
                continue;
            }

            $host = parse_url($url, PHP_URL_HOST) ?: '';

            $existing = MissingExtractor::where('url', $url)->first();

            if ($existing) {
                $existing->increment('occurrences');
                $existing->update([
                    'last_seen_at' => $now,
                    'user_id' => $userId ?? $existing->user_id,
                ]);
            } else {
                MissingExtractor::create([
                    'url' => $url,
                    'host' => $host,
                    'provider' => $source['provider'] ?? null,
                    'name' => $source['name'] ?? null,
                    'type' => $payload['type'] ?? null,
                    'tmdb_id' => $payload['tmdb_id'] ?? null,
                    'title' => $payload['title'] ?? null,
                    'season' => $payload['season'] ?? null,
                    'episode' => $payload['episode'] ?? null,
                    'user_id' => $userId,
                    'occurrences' => 1,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);
            }
        }
    }

    private function hasExtractor(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }
        $host = strtolower(preg_replace('/^www\./', '', $host));

        foreach (self::SUPPORTED_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain) || str_contains($host, explode('.', $domain)[0])) {
                return true;
            }
        }

        return false;
    }
}
