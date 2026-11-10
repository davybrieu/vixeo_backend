<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TvChannelsRequest;
use App\Services\Tv\LiveTv;
use Illuminate\Http\JsonResponse;
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
    public function __construct(private LiveTv $live) {}

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
     * Resolve a playable stream for a channel.
     *
     * The provider internals stay server-side; the app gets the HLS URL and the headers to send.
     */
    public function stream(string $id): JsonResponse
    {
        try {
            $stream = $this->live->stream($id);
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        return response()->json(['data' => $stream]);
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
