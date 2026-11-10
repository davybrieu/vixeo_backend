<?php

namespace App\Services\Tv;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Channel logos relayed through our own origin (`GET /tv/logo/{hash}`), like the streams.
 *
 * The providers' logo URLs can't be handed to the apps: Flutter web downloads images with fetch,
 * which needs CORS headers the logo hosts don't send; a release Android build refuses plain-HTTP
 * images; and the app must never see a provider's address. So each logo becomes a stable URL on
 * our API: the SHA-256 of the logo's address. Only that hash → address map is kept server-side
 * (text). A channel can have several candidates (the logo library, the programme guide, the
 * provider: LiveTv::channels): the relay serves the first one that gives a usable image.
 *
 * The image itself is **never stored** on our server (developer's request): it is fetched from the
 * provider when asked, checked (a real raster image, 512 KB at most) and passed on. The apps and
 * browsers keep it 7 days (HTTP cache, `TvController::logo`).
 */
class ChannelLogos
{
    private const MAP_KEY = 'tv:logos:map';

    private const MAP_TTL = 604800;

    private const MAX_BYTES = 524288;

    /** Image types the apps can draw (no SVG: Flutter's Image can't, and it may carry scripts). */
    private const TYPES = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_WEBP => 'image/webp', IMAGETYPE_GIF => 'image/gif'];

    /**
     * The channels, each logo (an address, or candidate addresses in order of preference)
     * replaced by its URL on our API (none: null).
     *
     * @param  list<array<string, mixed>>  $channels
     * @return list<array<string, mixed>>
     */
    public function rewrite(array $channels): array
    {
        $map = Cache::get(self::MAP_KEY, []);
        $map = is_array($map) ? $map : [];
        $added = false;

        foreach ($channels as $i => $channel) {
            $candidates = array_values(array_unique(array_filter(
                (array) ($channel['logo'] ?? null),
                fn ($url) => is_string($url) && preg_match('#^https?://#i', $url)
            )));
            if ($candidates === []) {
                $channels[$i]['logo'] = null;

                continue;
            }

            $logo = count($candidates) === 1 ? $candidates[0] : $candidates;
            $hash = substr(hash('sha256', implode("\n", $candidates)), 0, 32);
            if (($map[$hash] ?? null) !== $logo) {
                $map[$hash] = $logo;
                $added = true;
            }
            $channels[$i]['logo'] = route('v1.tv.logo', ['hash' => $hash]);
        }

        // One cache write when the provider shows new logos, none otherwise.
        if ($added) {
            Cache::put(self::MAP_KEY, $map, self::MAP_TTL);
        }

        return $channels;
    }

    /**
     * The logo image, fetched from the provider now (never stored), or null when unknown or not a
     * usable image.
     *
     * @return array{body: string, content_type: string}|null
     */
    public function image(string $hash): ?array
    {
        return $this->inspect($hash)['image'];
    }

    /**
     * Fetches the logo and says what happened, for the relay and `php artisan tv:logos`.
     *
     * @return array{image: array{body: string, content_type: string}|null, reason: string|null, host: string|null, status: int|null, content_type: string|null, bytes: int, url: string|null}
     */
    public function inspect(string $hash): array
    {
        $map = Cache::get(self::MAP_KEY, []);
        $urls = array_values(array_filter((array) (is_array($map) ? ($map[$hash] ?? null) : null), 'is_string'));
        if ($urls === []) {
            return ['image' => null, 'reason' => 'unknown logo (list the channels first)', 'host' => null, 'status' => null, 'content_type' => null, 'bytes' => 0, 'url' => null];
        }

        // The first candidate that gives a usable image.
        foreach ($urls as $url) {
            $result = $this->fetch($url);
            if ($result['reason'] === null) {
                return $result;
            }
            Log::info('Channel logo refused', array_diff_key($result, ['image' => true]));
        }

        return $result;
    }

    private function get(string $url): Response
    {
        return Http::timeout(8)->connectTimeout(5)->accept('image/*')->get($url);
    }

    /** @return array{image: array{body: string, content_type: string}|null, reason: string|null, host: string|null, status: int|null, content_type: string|null, bytes: int, url: string} */
    private function fetch(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? $host : null;
        $result = ['image' => null, 'reason' => null, 'host' => $host, 'status' => null, 'content_type' => null, 'bytes' => 0, 'url' => $url];

        try {
            $response = $this->get($url);
        } catch (Throwable $e) {
            // Some logo hosts break the TLS handshake but serve the same image over plain HTTP. The
            // app still gets it over HTTPS, from our origin: only this server talks HTTP to them.
            if (! str_starts_with(strtolower($url), 'https://')) {
                return ['reason' => 'unreachable: '.$e->getMessage()] + $result;
            }
            $plain = 'http://'.substr($url, strlen('https://'));
            try {
                $response = $this->get($plain);
                $result['url'] = $plain;
            } catch (Throwable $retry) {
                return ['reason' => 'unreachable over https ('.$e->getMessage().') and http ('.$retry->getMessage().')'] + $result;
            }
        }

        $body = $response->successful() ? $response->body() : '';
        $result = [
            'status' => $response->status(),
            'content_type' => $response->header('Content-Type') ?: null,
            'bytes' => strlen($body),
        ] + $result;
        if (! $response->successful()) {
            return ['reason' => 'HTTP '.$response->status()] + $result;
        }
        if ($body === '') {
            return ['reason' => 'empty response'] + $result;
        }
        if (strlen($body) > self::MAX_BYTES) {
            return ['reason' => 'too big (over 512 KB)'] + $result;
        }

        // Trust the bytes, not the Content-Type of the provider.
        $info = @getimagesizefromstring($body);
        $type = is_array($info) ? (self::TYPES[$info[2]] ?? null) : null;
        if ($type === null) {
            $svg = str_contains(strtolower(substr($body, 0, 1024)), '<svg');

            return ['reason' => $svg ? 'SVG image (not accepted)' : 'not a PNG, JPEG, WebP or GIF image'] + $result;
        }

        return ['image' => ['body' => $body, 'content_type' => $type]] + $result;
    }
}
