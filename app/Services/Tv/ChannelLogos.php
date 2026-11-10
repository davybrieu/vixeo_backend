<?php

namespace App\Services\Tv;

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
 * our API: the SHA-256 of the provider URL. Only that hash → URL map is kept server-side (text).
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
     * The channels, each logo replaced by its URL on our API (null stays null).
     *
     * @template T of array{logo: string|null}
     *
     * @param  list<T>  $channels
     * @return list<T>
     */
    public function rewrite(array $channels): array
    {
        $map = Cache::get(self::MAP_KEY, []);
        $map = is_array($map) ? $map : [];
        $added = false;

        foreach ($channels as $i => $channel) {
            $logo = $channel['logo'];
            if ($logo === null || ! preg_match('#^https?://#i', $logo)) {
                $channels[$i]['logo'] = null;

                continue;
            }

            $hash = substr(hash('sha256', $logo), 0, 32);
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
        $map = Cache::get(self::MAP_KEY, []);
        $url = is_array($map) ? ($map[$hash] ?? null) : null;

        return is_string($url) ? $this->fetch($url) : null;
    }

    /** @return array{body: string, content_type: string}|null */
    private function fetch(string $url): ?array
    {
        try {
            $response = Http::timeout(8)->connectTimeout(5)->get($url);
        } catch (Throwable $e) {
            Log::info('Channel logo unreachable', ['host' => parse_url($url, PHP_URL_HOST), 'error' => $e->getMessage()]);

            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $body = $response->successful() ? $response->body() : '';
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            Log::info('Channel logo refused', [
                'host' => $host,
                'status' => $response->status(),
                'bytes' => strlen($body),
                'reason' => $body === '' ? 'no image' : 'too big',
            ]);

            return null;
        }

        // Trust the bytes, not the Content-Type of the provider.
        $info = @getimagesizefromstring($body);
        $type = is_array($info) ? (self::TYPES[$info[2]] ?? null) : null;
        if ($type === null) {
            Log::info('Channel logo refused', [
                'host' => $host,
                'content_type' => $response->header('Content-Type'),
                'reason' => 'not a PNG, JPEG, WebP or GIF image',
            ]);

            return null;
        }

        return ['body' => $body, 'content_type' => $type];
    }
}
