<?php

namespace App\Http\Resources\V1\Playback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StreamSourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'provider' => (string) ($this['provider'] ?? ''),
            'name' => (string) ($this['name'] ?? ''),
            'url' => (string) ($this['url'] ?? ''),
            /** The address to play (plug-in streams are already playable). */
            'stream' => isset($this['stream']) ? (string) $this['stream'] : null,
            'stream_type' => isset($this['streamType']) ? (string) $this['streamType'] : null,
            /** Always null: the server has no extractor any more (kept for the former apps). */
            'extractor' => isset($this['extractor']) ? (string) $this['extractor'] : null,
            /**
             * HTTP headers the player must send with `stream` (plug-in streams, docs/plugins.md).
             *
             * @var array<string, string>
             */
            'headers' => (object) (is_array($this['headers'] ?? null) ? $this['headers'] : []),
        ];
    }
}
