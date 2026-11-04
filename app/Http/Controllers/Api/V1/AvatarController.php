<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Avatars\AvatarLicenseResource;
use App\Services\AvatarCatalog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Profils
 */
class AvatarController extends Controller
{
    /**
     * Avatars.
     *
     * Avatars a profile can use, grouped by license, sorted by name.
     */
    public function index(AvatarCatalog $catalog): AnonymousResourceCollection
    {
        return AvatarLicenseResource::collection($catalog->licenses());
    }
}
