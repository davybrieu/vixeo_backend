<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StreamSourcesRequest;
use App\Http\Resources\V1\Playback\StreamSourceResource;
use App\Models\User;
use App\Services\Addons\Addons;
use App\Services\Playback\PlaybackServiceUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sources of a movie or an episode: the streams of the plug-ins (Stremio add-ons, docs/plugins.md).
 * Vixeo has no stream provider of its own any more (developer's request): no scraper, no
 * extractor on the server.
 */
class StreamController extends Controller
{
    public function __construct(private Addons $addons) {}

    /**
     * Providers the sources panel shows, so the app can list them before any search runs: the
     * account's plug-ins that serve streams (`addon:12`, docs/plugins.md).
     *
     * @response array{data: list<array{id: string, name: string}>}
     */
    public function providers(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->addons->streamProviders($request->user('sanctum'))]);
    }

    /**
     * Links of a movie: the streams of one plug-in (`provider=addon:12`), already playable
     * (`stream`, `headers`), or of every plug-in without `provider`. Another provider (a former
     * provider of the server) has no link.
     */
    public function movieSources(StreamSourcesRequest $request, int $id): AnonymousResourceCollection
    {
        return StreamSourceResource::collection(
            $this->streams($request->user('sanctum'), $request->provider(), MediaType::Movie, $id)
        );
    }

    /** Links of an episode (see `movieSources`). */
    public function episodeSources(StreamSourcesRequest $request, int $id, int $season, int $episode): AnonymousResourceCollection
    {
        return StreamSourceResource::collection(
            $this->streams($request->user('sanctum'), $request->provider(), MediaType::Tv, $id, $season, $episode)
        );
    }

    /**
     * Resolve a player link into a playable stream (former apps).
     *
     * Always 502: the server no longer resolves links (no extractor). Plug-in streams are already
     * playable.
     *
     * @deprecated
     */
    public function resolve(): JsonResponse
    {
        return response()->json(['message' => __('This source cannot be played.')], 502);
    }

    /** @return list<array<string, mixed>> */
    private function streams(?User $user, ?string $provider, MediaType $type, int $id, ?int $season = null, ?int $episode = null): array
    {
        if ($provider !== null) {
            return Addons::isAddonProvider($provider)
                ? $this->addons->streams($user, $provider, $type, $id, $season, $episode)
                : [];
        }

        // Every plug-in, in their order; one that fails leaves the others.
        $streams = [];
        foreach ($this->addons->streamProviders($user) as $addon) {
            try {
                array_push($streams, ...$this->addons->streams($user, $addon['id'], $type, $id, $season, $episode));
            } catch (PlaybackServiceUnavailable) {
                continue;
            }
        }

        return $streams;
    }
}
