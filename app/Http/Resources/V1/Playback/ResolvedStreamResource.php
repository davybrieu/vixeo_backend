<?php

namespace App\Http\Resources\V1\Playback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A player page URL resolved into a playable stream (m3u8 / mp4) by the scraper's extractors.
 *
 * @property array{stream: string, stream_type: string|null, extractor: string|null, headers: array<string, string>, subtitles: list<mixed>} $resource
 */
class ResolvedStreamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            /** Playable stream URL (HLS `.m3u8` or progressive `.mp4`). */
            'stream' => (string) $this->resource['stream'],
            /** "m3u8" or "mp4". */
            'stream_type' => $this->resource['stream_type'],
            /** Extractor that resolved the link ("voe", "streamtape"…), null for the generic one. */
            'extractor' => $this->resource['extractor'],
            /**
             * HTTP headers the player must send when fetching the stream (Referer, Origin…).
             *
             * @var array<string, string>
             */
            'headers' => (object) $this->resource['headers'],
            /**
             * Subtitles found next to the stream, when the extractor provides some.
             *
             * @var list<mixed>
             */
            'subtitles' => $this->resource['subtitles'],
        ];
    }
}
