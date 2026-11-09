<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VavooChannelsRequest;
use App\Http\Resources\V1\Playback\VavooCategoryResource;
use App\Http\Resources\V1\Playback\VavooChannelResource;
use App\Http\Resources\V1\Playback\VavooStreamResource;
use App\Services\Vavoo\VavooClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Vavoo live TV (docs/tv-vavoo.md). The signature, the mirrors and the channel URLs stay on the
 * server: the app receives channels and a playable HLS URL, never the Vavoo internals.
 *
 * @tags TV en direct
 */
class VavooController extends Controller
{
    public function __construct(private VavooClient $vavoo) {}

    /**
     * Channel categories.
     *
     * The French categories the channels are sorted into (Sport, Cinéma, Info…).
     */
    public function categories(): AnonymousResourceCollection
    {
        try {
            $categories = $this->vavoo->categories();
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        return VavooCategoryResource::collection($categories);
    }

    /**
     * Channels.
     *
     * Optionally filtered by category_id, and by a name search with q.
     */
    public function channels(VavooChannelsRequest $request): AnonymousResourceCollection
    {
        $categoryId = $request->categoryId();
        $q = $request->search();

        try {
            $channels = $this->vavoo->channelsByCategory($categoryId);
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $channels = array_values(array_filter(
                $channels,
                fn (array $c) => str_contains(mb_strtolower($c['name']), $needle)
            ));
        }

        return VavooChannelResource::collection($channels);
    }

    /**
     * Resolve a playable stream for a channel.
     *
     * The signature and the channel URL stay server-side; the app gets the HLS URL and the
     * headers to send.
     */
    public function stream(string $id): VavooStreamResource|JsonResponse
    {
        try {
            $streams = $this->vavoo->streams($id);
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        return new VavooStreamResource([
            'url' => $streams[0],
            'format' => 'hls',
            'headers' => $this->vavoo->streamHeaders(),
        ]);
    }

    private function rethrow(RuntimeException $e): never
    {
        $message = $e->getMessage();

        if ($message === __('Vavoo is not configured.') || $message === __('Vavoo is temporarily unavailable.')) {
            throw new ServiceUnavailableHttpException(null, $message);
        }
        if ($message === __('Channel not found.')) {
            // HttpException (not NotFoundHttpException) so the specific message is kept.
            throw new HttpException(404, $message, $e);
        }

        throw new HttpException(502, $message, $e);
    }
}
