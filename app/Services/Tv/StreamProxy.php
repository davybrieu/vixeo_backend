<?php

namespace App\Services\Tv;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Streams a provider's media through our own origin.
 *
 * Some providers only publish plain-HTTP stream URLs. Handing those straight to the app breaks
 * playback: a page served over HTTPS blocks them (mixed content) and a release Android build
 * refuses cleartext traffic. So the app gets a URL on our own API (HTTPS): the playlist and its
 * segments are fetched server-side and streamed back. The provider URL, its User-Agent and any
 * signature stay on the server, hidden behind a signed, opaque token.
 *
 * The token is encrypted with APP_KEY, so only this server can mint one. It therefore doubles as
 * the authorisation for the media route, which the player loads without a bearer token (a
 * `<video>` element and ExoPlayer send none). Tokens expire, so a copied URL stops working.
 */
class StreamProxy
{
    /**
     * Playable URL the app should call for a resolved stream (absolute, on our own origin).
     *
     * The URL keeps a media extension (`hls` → `.m3u8`, `ts` → `.ts`): on Android, ExoPlayer
     * infers the stream type from the URL extension, so dropping it would break HLS playback.
     *
     * @param  array<string, string>  $headers  headers the provider expects (kept server-side)
     */
    public function playUrl(string $url, array $headers = [], string $format = 'hls'): string
    {
        $url = route('v1.tv.stream.media', ['token' => $this->encode($url, $headers)]);

        return $url.match ($format) {
            'ts' => '.ts',
            default => '.m3u8',
        };
    }

    /**
     * Serves one media resource through us.
     *
     * An HLS playlist is rewritten so its URIs point back to us; anything else (a segment, a
     * variant playlist, a raw MPEG-TS stream) is passed through untouched.
     *
     * @return array{body: string, content_type: string}
     */
    public function media(string $token): array
    {
        // The play URL carries a media extension for the player; the token itself never has one.
        $token = preg_replace('/\.(m3u8|ts|m4s|aac|vtt|key)$/i', '', $token) ?? $token;

        [$url, $headers] = $this->decode($token);

        $response = $this->request($url, $headers);
        $contentType = $response->header('Content-Type') ?: $this->guessContentType($url);
        $body = $response->body();

        if ($this->isPlaylist($url, $contentType)) {
            $body = $this->rewrite($body, $url, $headers);
            $contentType = 'application/vnd.apple.mpegurl';
        }

        return ['body' => $body, 'content_type' => $contentType];
    }

    /**
     * Fetches a provider URL, mapping failures to a neutral error.
     *
     * @param  array<string, string>  $headers
     */
    private function request(string $url, array $headers): ClientResponse
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException(__('Stream unavailable.'));
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout((int) config('services.tv.proxy_timeout', 20))
                ->get($url);
        } catch (Throwable) {
            throw new RuntimeException(__('Stream unavailable.'));
        }

        if ($response->failed()) {
            throw new RuntimeException(__('Stream unavailable.'));
        }

        return $response;
    }

    /**
     * Rewrites every URI of a playlist so the player loads it from us.
     *
     * @param  array<string, string>  $headers
     */
    private function rewrite(string $playlist, string $baseUrl, array $headers): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $playlist) ?: [];

        return implode("\n", array_map(function (string $line) use ($baseUrl, $headers): string {
            $trimmed = trim($line);

            if ($trimmed === '') {
                return $line;
            }

            // Tag: rewrite the URI="…" attributes (encryption keys, media renditions).
            if (str_starts_with($trimmed, '#')) {
                return preg_replace_callback(
                    '/URI="([^"]+)"/',
                    fn (array $m): string => 'URI="'.$this->mediaUrl($this->absolute($m[1], $baseUrl), $headers).'"',
                    $line,
                ) ?? $line;
            }

            // A bare URI line: a media segment or a variant playlist.
            return $this->mediaUrl($this->absolute($trimmed, $baseUrl), $headers);
        }, $lines));
    }

    /**
     * Our own (relative) URL for a provider resource, carrying the signed token.
     *
     * A media extension is appended for the player (ExoPlayer reads it): `.m3u8` for a playlist,
     * `.ts` otherwise. The controller strips it before decoding the token.
     *
     * @param  array<string, string>  $headers
     */
    private function mediaUrl(string $url, array $headers): string
    {
        $route = route('v1.tv.stream.media', ['token' => $this->encode($url, $headers)], absolute: false);
        $isPlaylist = str_ends_with((string) parse_url($url, PHP_URL_PATH), '.m3u8');

        return $route.($isPlaylist ? '.m3u8' : '.ts');
    }

    /** Resolves a playlist-relative URI against the playlist URL. */
    private function absolute(string $uri, string $baseUrl): string
    {
        return (string) UriResolver::resolve(new Uri($baseUrl), new Uri($uri));
    }

    private function isPlaylist(string $url, string $contentType): bool
    {
        if (str_contains(strtolower($contentType), 'mpegurl')) {
            return true;
        }

        return str_ends_with((string) parse_url($url, PHP_URL_PATH), '.m3u8');
    }

    private function guessContentType(string $url): string
    {
        return str_ends_with((string) parse_url($url, PHP_URL_PATH), '.m3u8')
            ? 'application/vnd.apple.mpegurl'
            : 'video/mp2t';
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function encode(string $url, array $headers): string
    {
        $payload = json_encode([
            'u' => $url,
            'h' => $headers,
            'e' => time() + (int) config('services.tv.proxy_ttl', 21600),
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(Crypt::encryptString($payload), '+/', '-_'), '=');
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function decode(string $token): array
    {
        $base64 = strtr($token, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);

        try {
            $payload = Crypt::decryptString($base64);
            /** @var array<string, mixed>|null $data */
            $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException(__('Stream unavailable.'));
        }

        if (! is_array($data) || ! isset($data['u'], $data['e']) || (int) $data['e'] < time()) {
            throw new RuntimeException(__('Stream unavailable.'));
        }

        /** @var array<string, string> $headers */
        $headers = is_array($data['h'] ?? null) ? $data['h'] : [];

        return [(string) $data['u'], $headers];
    }
}
