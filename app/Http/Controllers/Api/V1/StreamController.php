<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResolveStreamRequest;
use App\Http\Requests\Api\V1\StreamSourcesRequest;
use App\Http\Resources\V1\Playback\ResolvedStreamResource;
use App\Http\Resources\V1\Playback\StreamSourceResource;
use App\Services\Addons\Addons;
use App\Services\Playback\StreamSources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StreamController extends Controller
{
    public function __construct(private StreamSources $sources, private Addons $addons) {}

    /**
     * Providers the sources panel shows, so the app can list them before any search runs: those
     * of the server, then the account's plug-ins that serve streams (`addon:12`, docs/plugins.md).
     *
     * @response array{data: list<array{id: string, name: string}>}
     */
    public function providers(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            ...$this->sources->providers(),
            ...$this->addons->streamProviders($request->user('sanctum')),
        ]]);
    }

    /**
     * Links of a movie. A plug-in provider (`addon:12`) gives streams already playable
     * (`stream`, `headers`): no `resolve` needed.
     */
    public function movieSources(StreamSourcesRequest $request, int $id): AnonymousResourceCollection
    {
        $provider = $request->provider();
        if (Addons::isAddonProvider($provider)) {
            return StreamSourceResource::collection(
                $this->addons->streams($request->user('sanctum'), (string) $provider, MediaType::Movie, $id)
            );
        }

        return StreamSourceResource::collection(
            $this->sources->movie($id, $request->extract(), $provider)
        );
    }

    /** Links of an episode (see `movieSources`). */
    public function episodeSources(StreamSourcesRequest $request, int $id, int $season, int $episode): AnonymousResourceCollection
    {
        $provider = $request->provider();
        if (Addons::isAddonProvider($provider)) {
            return StreamSourceResource::collection(
                $this->addons->streams($request->user('sanctum'), (string) $provider, MediaType::Tv, $id, $season, $episode)
            );
        }

        return StreamSourceResource::collection(
            $this->sources->episode($id, $season, $episode, $request->extract(), $provider)
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
