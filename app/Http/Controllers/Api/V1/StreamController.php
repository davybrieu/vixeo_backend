<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Playback\StreamSourceResource;
use App\Services\Playback\StreamSources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StreamController extends Controller
{
    public function __construct(private StreamSources $sources) {}

    public function movieSources(Request $request, int $id): AnonymousResourceCollection
    {
        return StreamSourceResource::collection(
            $this->sources->movie($id, $request->boolean('extract'))
        );
    }

    public function episodeSources(Request $request, int $id, int $season, int $episode): AnonymousResourceCollection
    {
        return StreamSourceResource::collection(
            $this->sources->episode($id, $season, $episode, $request->boolean('extract'))
        );
    }
}
