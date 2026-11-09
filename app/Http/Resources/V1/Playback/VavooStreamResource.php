<?php

namespace App\Http\Resources\V1\Playback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A playable stream for a Vavoo channel: the HLS URL and the headers the player must send.
 *
 * @property array{url: string, format: string, headers: array<string, string>} $resource
 */
class VavooStreamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $s = $this->resource;

        return [
            /** Playable stream URL (HLS `.m3u8`). */
            'url' => (string) $s['url'],
            /** "hls". */
            'format' => (string) $s['format'],
            /**
             * HTTP headers the player must send when fetching the stream (User-Agent…).
             *
             * @var array<string, string>
             */
            'headers' => (object) $s['headers'],
        ];
    }
}
