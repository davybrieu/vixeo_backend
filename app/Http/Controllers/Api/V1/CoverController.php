<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Covers\CoverStyleResource;
use App\Services\CoverCatalog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Profils
 */
class CoverController extends Controller
{
    /**
     * Covers.
     *
     * The covers a profile can use (the banner of its page), grouped by style. Each is drawn by the
     * apps from its pattern and its colors. A new profile gets one at random.
     */
    public function index(CoverCatalog $catalog): AnonymousResourceCollection
    {
        return CoverStyleResource::collection($catalog->styles());
    }
}
