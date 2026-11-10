<?php

namespace App\Services\Tv;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Channel logos served from our own origin (`GET /tv/logo/{hash}`), like the streams.
 *
 * The providers' logo URLs can't be handed to the apps: Flutter web downloads images with fetch,
 * which needs CORS headers the logo hosts don't send; a release Android build refuses plain-HTTP
 * images; and the app must never see a provider's address. So each logo becomes a stable URL on
 * our API: the SHA-256 of the provider URL, kept server-side in the cache. Stable, so the apps and
 * browsers can cache it. The image is fetched once, checked (a real raster image, 512 KB at most)
 * and kept a week.
 */
class ChannelLogos
{
    private const MAP_KEY = 'tv:logos:map';

    private const MAP_TTL = 604800;

    private const IMAGE_TTL = 604800;

    /** A logo that can't be fetched is retried after an hour. */
    private const FAILURE_TTL = 3600;

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
     * The logo image, or null when unknown or not a usable image.
     *
     * @return array{body: string, content_type: string}|null
     */
    public function image(string $hash): ?array
    {
        $cached = Cache::get("tv:logos:image:{$hash}");
        if (is_array($cached)) {
            return $cached;
        }
        if ($cached === false) {
            return null;
        }

        $map = Cache::get(self::MAP_KEY, []);
        $url = is_array($map) ? ($map[$hash] ?? null) : null;
        if (! is_string($url)) {
            return null;
        }

        $image = $this->fetch($url);
        Cache::put("tv:logos:image:{$hash}", $image ?? false, $image === null ? self::FAILURE_TTL : self::IMAGE_TTL);

        return $image;
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

        $body = $response->successful() ? $response->body() : '';
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        // Trust the bytes, not the Content-Type of the provider.
        $info = @getimagesizefromstring($body);
        $type = is_array($info) ? (self::TYPES[$info[2]] ?? null) : null;

        return $type === null ? null : ['body' => $body, 'content_type' => $type];
    }
}
