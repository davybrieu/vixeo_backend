<?php

namespace App\Services\Playback;

use App\Services\Tmdb\TmdbClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StreamSources
{
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

        return is_array($sources) ? array_values($sources) : [];
    }
}
