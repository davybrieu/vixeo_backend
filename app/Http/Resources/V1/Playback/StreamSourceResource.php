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
            // Renseignés seulement quand l'extraction est demandée (`?extract=1`)
            // et que le lien du lecteur a pu être résolu en flux lisible.
            'stream' => isset($this['stream']) ? (string) $this['stream'] : null,
            'stream_type' => isset($this['streamType']) ? (string) $this['streamType'] : null,
            'extractor' => isset($this['extractor']) ? (string) $this['extractor'] : null,
        ];
    }
}
