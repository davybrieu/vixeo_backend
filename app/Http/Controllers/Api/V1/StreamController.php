<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResolveStreamRequest;
use App\Http\Resources\V1\Playback\ResolvedStreamResource;
use App\Http\Resources\V1\Playback\StreamSourceResource;
use App\Services\Playback\StreamSources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StreamController extends Controller
{
    public function __construct(private StreamSources $sources) {}

    public function movieSources(Request $request, int $id): AnonymousResourceCollection
    {
        return StreamSourceResource::collection(
            $this->sources->movie($id, $request->boolean('extract'))
        );
    }

    public function episodeSources(Request $request, int $id, int $season, int $episode): AnonymousResourceCollection
    {
        return StreamSourceResource::collection(
            $this->sources->episode($id, $season, $episode, $request->boolean('extract'))
        );
    }

    /**
     * Resolve a player link into a playable stream.
     *
     * Runs the scraper's extractor for the link's host and returns the HLS/MP4 stream the
     * player should load, with the headers it must send. 502 when the link cannot be resolved
     * (unknown host, extraction failure): the app then shows an error instead of the raw link.
     */
    public function resolve(ResolveStreamRequest $request): ResolvedStreamResource|JsonResponse
    {
        $resolved = $this->sources->resolve($request->validated('url'));

        if ($resolved === null) {
            return response()->json(['message' => 'Cette source n’est pas lisible.'], 502);
        }

        return new ResolvedStreamResource($resolved);
    }
}
