<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaType;
use App\Http\Controllers\Api\V1\Concerns\ResolvesSocialProfiles;
use App\Http\Controllers\Controller;
use App\Services\Social\TitleSocial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The social part of a title page (docs/social.md).
 *
 * @tags Social
 */
class TitleSocialController extends Controller
{
    use ResolvesSocialProfiles;

    public function __construct(private TitleSocial $social) {}

    /**
     * Who watched it, reviews and lists.
     *
     * `friends`: followed profiles who watched or rated it (`rating` null when only watched) and
     * their average; `reviews`: the 3 most liked with a comment (`reviews_count` in all); `lists`:
     * lists that have it, the most liked first.
     */
    public function show(Request $request, MediaType $type, int $id): JsonResponse
    {
        return response()->json(['data' => $this->social->show($this->viewer($request), $type, $id)]);
    }

    /**
     * Every review of a title.
     *
     * With a comment; `sort`: `popular` (the most liked, default) or `recent`.
     */
    public function reviews(Request $request, MediaType $type, int $id): JsonResponse
    {
        $sort = $request->query('sort') === 'recent' ? 'recent' : 'popular';

        return response()->json($this->social->reviews($this->viewer($request), $type, $id, $sort));
    }
}
