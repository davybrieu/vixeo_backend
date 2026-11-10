<?php

namespace App\Services\Xtream;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for a personal legal Xtream Codes panel.
 * Credentials never leave the server.
 */
class XtreamClient
{
    public function configured(): bool
    {
        $c = config('services.xtream');

        return filled($c['base_url'] ?? null)
            && filled($c['username'] ?? null)
            && filled($c['password'] ?? null);
    }

    /**
     * @return list<array{id: string, name: string, parent_id: string|null}>
     */
    public function liveCategories(): array
    {
        $this->ensureConfigured();

        return Cache::remember('xtream:live_categories', (int) config('services.xtream.ttl.categories'), function () {
            $rows = $this->get('get_live_categories');

            return collect($rows)
                ->map(fn ($row) => [
                    'id' => (string) ($row['category_id'] ?? ''),
                    'name' => (string) ($row['category_name'] ?? ''),
                    'parent_id' => isset($row['parent_id']) && (string) $row['parent_id'] !== '0'
                        ? (string) $row['parent_id']
                        : null,
                ])
                ->filter(fn ($row) => $row['id'] !== '' && $row['name'] !== '')
                ->values()
                ->all();
        });
    }

    /**
     * @return list<array{id: string, name: string, category_id: string, logo: string|null, number: int|null}>
     */
    public function liveChannels(?string $categoryId = null): array
    {
        $this->ensureConfigured();

        $cacheKey = 'xtream:live_streams:'.($categoryId ?? 'all');

        return Cache::remember($cacheKey, (int) config('services.xtream.ttl.channels'), function () use ($categoryId) {
            $rows = $this->get('get_live_streams');

            return collect($rows)
                ->when($categoryId !== null && $categoryId !== '', function ($collection) use ($categoryId) {
                    return $collection->where('category_id', $categoryId);
                })
                ->map(fn ($row) => [
                    'id' => (string) ($row['stream_id'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'category_id' => (string) ($row['category_id'] ?? ''),
                    'logo' => filled($row['stream_icon'] ?? null) ? (string) $row['stream_icon'] : null,
                    'number' => isset($row['num']) ? (int) $row['num'] : null,
                ])
                ->filter(fn ($row) => $row['id'] !== '' && $row['name'] !== '')
                ->values()
                ->all();
        });
    }

    /**
     * Build the direct playable stream URL for a live channel.
     * Credentials stay in the URL path (Xtream convention); the client receives this URL only.
     *
     * @return array{url: string, format: string, headers: array<string, string>}
     */
    public function liveStream(string $streamId): array
    {
        $this->ensureConfigured();

        $streamId = trim($streamId);
        if ($streamId === '' || ! ctype_digit($streamId)) {
            throw new RuntimeException(__('Channel not found.'));
        }

        // Ensure the channel exists on the panel (and warm cache).
        $channels = $this->liveChannels();
        $found = collect($channels)->firstWhere('id', $streamId);
        if ($found === null) {
            // Force refresh once in case the cache is stale.
            Cache::forget('xtream:live_streams:all');
            $channels = $this->liveChannels();
            $found = collect($channels)->firstWhere('id', $streamId);
        }
        if ($found === null) {
            throw new RuntimeException(__('Channel not found.'));
        }

        $base = rtrim((string) config('services.xtream.base_url'), '/');
        $user = rawurlencode((string) config('services.xtream.username'));
        $pass = rawurlencode((string) config('services.xtream.password'));
        $format = strtolower((string) config('services.xtream.stream_format', 'm3u8'));
        if (! in_array($format, ['m3u8', 'ts'], true)) {
            $format = 'm3u8';
        }

        // Standard Xtream live path.
        $url = sprintf('%s/live/%s/%s/%s.%s', $base, $user, $pass, $streamId, $format);

        return [
            'url' => $url,
            'format' => $format === 'm3u8' ? 'hls' : 'ts',
            'headers' => [],
        ];
    }

    public function forgetCache(): void
    {
        Cache::forget('xtream:live_categories');
        // Best-effort: known category keys are not enumerated; flush all stream lists on demand via prefix if Redis.
        Cache::forget('xtream:live_streams:all');
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException(__('Live TV is not configured.'));
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function get(string $action): array
    {
        $base = rtrim((string) config('services.xtream.base_url'), '/');
        $timeout = (int) config('services.xtream.timeout', 20);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($base.'/player_api.php', [
                    'username' => config('services.xtream.username'),
                    'password' => config('services.xtream.password'),
                    'action' => $action,
                ])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new RuntimeException(__('Unable to reach the live TV server.'), 0, $e);
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException(__('Invalid response from the live TV server.'));
        }

        // Xtream sometimes returns an object keyed by id; normalize to a list.
        if ($data !== [] && array_keys($data) !== range(0, count($data) - 1)) {
            $data = array_values($data);
        }

        return $data;
    }
}
