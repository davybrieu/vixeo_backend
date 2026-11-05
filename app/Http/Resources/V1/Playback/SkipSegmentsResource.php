<?php

namespace App\Http\Resources\V1\Playback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Moments the player can skip, in milliseconds. A null `start_ms` means "from the beginning", a
 * null `end_ms` "to the end". Empty lists when nobody has timed the title yet.
 *
 * @property array<string, list<array{start_ms: int|null, end_ms: int|null}>> $resource
 */
class SkipSegmentsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            /**
             * Opening credits ("générique de début").
             *
             * @var list<array{start_ms: int|null, end_ms: int|null}>
             */
            'intro' => $this->resource['intro'],
            /**
             * Recap of the previous episodes ("Précédemment…").
             *
             * @var list<array{start_ms: int|null, end_ms: int|null}>
             */
            'recap' => $this->resource['recap'],
            /**
             * End credits ("générique de fin").
             *
             * @var list<array{start_ms: int|null, end_ms: int|null}>
             */
            'credits' => $this->resource['credits'],
            /**
             * Preview of the next episode.
             *
             * @var list<array{start_ms: int|null, end_ms: int|null}>
             */
            'preview' => $this->resource['preview'],
        ];
    }
}
