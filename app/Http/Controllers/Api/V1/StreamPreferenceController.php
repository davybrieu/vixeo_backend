<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FavoriteStreamRequest;
use App\Http\Requests\Api\V1\StreamContentRequest;
use App\Http\Requests\Api\V1\WorkedStreamRequest;
use App\Http\Resources\V1\Playback\StreamPreferenceResource;
use App\Models\Profile;
use App\Services\Playback\DeadStreams;
use App\Services\Playback\StreamPreferences;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Stream preferences of a profile for one content: favorites (❤, tried first) and the last stream
 * that worked (tried next). See docs/lecture.md.
 *
 * @tags Lecture
 */
class StreamPreferenceController extends Controller
{
    public function __construct(private StreamPreferences $preferences, private DeadStreams $dead) {}

    /**
     * Preferences of a content.
     *
     * Favorites first, then the most recently working. The player tries them in this order before
     * the plain source list.
     */
    public function index(StreamContentRequest $request, Profile $profile): AnonymousResourceCollection
    {
        Gate::authorize('view', $profile);

        return StreamPreferenceResource::collection(
            $this->preferences->forContent($profile, $request->validated('source'), $request->validated('item_id'))
        );
    }

    /**
     * Add or remove a stream favorite (❤).
     */
    public function favorite(FavoriteStreamRequest $request, Profile $profile): StreamPreferenceResource
    {
        Gate::authorize('update', $profile);

        $stream = $this->preferences->setFavorite(
            $profile,
            $request->validated('source'),
            $request->validated('item_id'),
            $request->validated('stream_id'),
            $request->boolean('favorite'),
        );

        return new StreamPreferenceResource($stream);
    }

    /**
     * Report that a stream worked.
     *
     * Remembered per profile so the player tries it first next time. Also clears the stream from
     * the dead cache, since it clearly works again.
     */
    public function worked(WorkedStreamRequest $request, Profile $profile): StreamPreferenceResource
    {
        Gate::authorize('update', $profile);

        $source = $request->validated('source');
        $streamId = $request->validated('stream_id');

        $stream = $this->preferences->markWorked(
            $profile,
            $source,
            $request->validated('item_id'),
            $streamId,
            $request->safe()->only(['stream_url', 'stream_type', 'extractor', 'quality', 'language']),
        );

        $this->dead->revive($source, $streamId);

        return new StreamPreferenceResource($stream);
    }
}
