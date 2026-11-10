<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TvChannelsRequest;
use App\Services\Tv\ChannelLogos;
use App\Services\Tv\LiveTv;
use App\Services\Tv\StreamProxy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Live TV, behind one provider-neutral API.
 *
 * The app calls `/tv/…` and never knows which provider (Vavoo, a personal Xtream panel…)
 * serves the channels: this controller delegates to {@see LiveTv}, which picks the configured
 * provider and normalises the shape. Credentials, mirrors and signatures stay server-side.
 *
 * @tags TV en direct
 */
class TvController extends Controller
{
    public function __construct(private LiveTv $live, private StreamProxy $proxy) {}

    /**
     * Channel categories.
     *
     * The categories the channels are sorted into (Sport, Cinéma, Info…).
     */
    public function categories(): JsonResponse
    {
        try {
            $categories = $this->live->categories();
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        return response()->json(['data' => $categories]);
    }

    /**
     * Channels.
     *
     * Optionally filtered by category_id, and by a name search with q.
     */
    public function channels(TvChannelsRequest $request): JsonResponse
    {
        $categoryId = $request->categoryId();
        $q = $request->search();

        try {
            $channels = $this->live->channels();
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        if ($categoryId !== null && $categoryId !== '') {
            $channels = array_values(array_filter(
                $channels,
                fn (array $c) => $c['category_id'] === $categoryId
            ));
        }

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $channels = array_values(array_filter(
                $channels,
                fn (array $c) => str_contains(mb_strtolower($c['name']), $needle)
            ));
        }

        return response()->json(['data' => array_values($channels)]);
    }

    /**
     * One channel.
     *
     * Its name, category and logo (an image on our origin, `GET /tv/logo/{hash}`, from the logo
     * library, the programme guide or the provider; null when none has one): the channel page of
     * the app.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $channel = $this->live->channel($id);
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        return response()->json(['data' => $channel]);
    }

    /**
     * Programme guide of a channel.
     *
     * The programmes from the one on now, in order (30 at most, the next 48 hours), with UTC
     * times. `available` is false when the server has no guide; `data` is also empty when the
     * guide doesn't know the channel, or while it is being downloaded for the first time.
     */
    public function epg(string $id): JsonResponse
    {
        try {
            $programmes = $this->live->programmes($id);
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        return response()->json([
            /** @var list<array{title: string, subtitle: string|null, description: string|null, category: string|null, image: string|null, start: string, end: string}> */
            'data' => $programmes,
            'available' => $this->live->hasGuide(),
        ]);
    }

    /**
     * Resolve a playable stream for a channel.
     *
     * The provider internals stay server-side. The app receives a playlist URL on our own origin
     * (so it works over HTTPS even when the provider only serves plain HTTP) and no headers: the
     * player loads the media itself, through the token in the URL.
     */
    public function stream(string $id): JsonResponse
    {
        try {
            $stream = $this->live->stream($id);
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        return response()->json(['data' => [
            'url' => $this->proxy->playUrl($stream['url'], $stream['headers'], $stream['format']),
            'format' => $stream['format'],
            'headers' => [],
        ]]);
    }

    /**
     * Media of a channel, proxied (HLS playlist and its segments).
     *
     * The player loads this URL without a bearer token: the signed, opaque token in the path is
     * what authorises it. An HLS playlist is rewritten so its URIs point back to this server.
     */
    public function media(string $token): Response
    {
        try {
            $media = $this->proxy->media($token);
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        $isPlaylist = str_contains($media['content_type'], 'mpegurl');

        return response($media['body'], 200, [
            'Content-Type' => $media['content_type'],
            // A live playlist changes every few seconds: never cache it. Segments are immutable.
            'Cache-Control' => $isPlaylist ? 'no-store' : 'private, max-age=30',
        ]);
    }

    /**
     * Logo of a channel.
     *
     * The image (PNG, JPEG, WebP or GIF), served from our origin: the `logo` URL of a channel.
     * 404 when the provider has no usable image (the app then shows its own icon).
     *
     * @unauthenticated
     */
    public function logo(string $hash, ChannelLogos $logos): Response
    {
        $image = $logos->image($hash);
        abort_if($image === null, 404);

        return response($image['body'], 200, [
            'Content-Type' => $image['content_type'],
            // The URL is stable for a given logo: the apps and browsers keep it 7 days. Our server
            // keeps nothing (ChannelLogos).
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function rethrow(RuntimeException $e): never
    {
        $message = $e->getMessage();

        // The provider is missing or unreachable: the app should show "unavailable", not an error.
        if (in_array($message, [
            __('Live TV is not configured.'),
            __('Live TV is temporarily unavailable.'),
            __('Unable to reach the live TV server.'),
        ], true)) {
            throw new ServiceUnavailableHttpException(null, $message);
        }

        // HttpException (not NotFoundHttpException) so the specific message is kept.
        if ($message === __('Channel not found.')) {
            throw new HttpException(404, $message, $e);
        }

        throw new HttpException(502, $message, $e);
    }
}
