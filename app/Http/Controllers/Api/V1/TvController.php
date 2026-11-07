<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Xtream\XtreamClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class TvController extends Controller
{
    public function __construct(private XtreamClient $xtream) {}

    /**
     * Live TV categories from the configured Xtream panel.
     */
    public function categories(): JsonResponse
    {
        try {
            $categories = $this->xtream->liveCategories();
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Live channels, optionally filtered by category_id.
     */
    public function channels(Request $request): JsonResponse
    {
        $categoryId = $request->query('category_id');
        $categoryId = is_string($categoryId) && $categoryId !== '' ? $categoryId : null;
        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';

        try {
            $channels = $this->xtream->liveChannels($categoryId);
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

        return response()->json([
            'data' => $channels,
        ]);
    }

    /**
     * Resolve a playable stream URL for a live channel (credentials stay server-side in the built URL).
     */
    public function stream(string $id): JsonResponse
    {
        try {
            $stream = $this->xtream->liveStream($id);
        } catch (RuntimeException $e) {
            $this->rethrow($e);
        }

        return response()->json([
            'data' => $stream,
        ]);
    }

    private function rethrow(RuntimeException $e): never
    {
        $message = $e->getMessage();

        if ($message === __('Live TV is not configured.')) {
            throw new ServiceUnavailableHttpException(null, $message);
        }
        if ($message === __('Channel not found.')) {
            throw new NotFoundHttpException($message);
        }

        throw new HttpException(502, $message, $e);
    }
}
