<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Playback\StreamSourceResource;
use App\Services\Playback\StreamSources;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StreamController extends Controller
{
    public function __construct(private StreamSources $sources) {}

    public function movieSources(int $id): AnonymousResourceCollection
    {
        return StreamSourceResource::collection($this->sources->movie($id));
    }

    public function episodeSources(int $id, int $season, int $episode): AnonymousResourceCollection
    {
        return StreamSourceResource::collection($this->sources->episode($id, $season, $episode));
    }
}
