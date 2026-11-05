<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubtitlesRequest;
use App\Http\Resources\V1\Playback\SkipSegmentsResource;
use App\Http\Resources\V1\Playback\SubtitleResource;
use App\Services\Playback\OpenSubtitles;
use App\Services\Playback\SkipSegments;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * What the player needs besides the video: moments to skip and subtitles (docs/lecture.md).
 *
 * @tags Lecture
 */
class PlaybackController extends Controller
{
    public function __construct(private SkipSegments $segments, private OpenSubtitles $subtitles) {}

    /**
     * Moments to skip in a movie.
     *
     * From TheIntroDB. The profile's `skip_*` settings tell whether the player skips them by
     * itself or shows a "Passer" button.
     */
    public function movieSegments(int $id): SkipSegmentsResource
    {
        return new SkipSegmentsResource($this->segments->movie($id));
    }

    /**
     * Moments to skip in a TV episode.
     */
    public function episodeSegments(int $id, int $season, int $episode): SkipSegmentsResource
    {
        return new SkipSegmentsResource($this->segments->episode($id, $season, $episode));
    }

    /**
     * Subtitles of a movie.
     *
     * From OpenSubtitles, preferred language first, then the most downloaded (30 max).
     */
    public function movieSubtitles(SubtitlesRequest $request, int $id): AnonymousResourceCollection
    {
        return SubtitleResource::collection($this->subtitles->search($id, null, null, $request->languages()));
    }

    /**
     * Subtitles of a TV episode.
     */
    public function episodeSubtitles(SubtitlesRequest $request, int $id, int $season, int $episode): AnonymousResourceCollection
    {
        return SubtitleResource::collection($this->subtitles->search($id, $season, $episode, $request->languages()));
    }

    /**
     * Subtitle file (WebVTT).
     *
     * The text of a subtitle found with the subtitle search, as `text/vtt` (UTF-8). Each file is
     * fetched from OpenSubtitles once, then served from our cache.
     */
    public function subtitleFile(int $file): Response
    {
        return response($this->subtitles->file($file), 200, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
