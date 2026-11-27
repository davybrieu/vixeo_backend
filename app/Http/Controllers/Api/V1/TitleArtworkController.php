<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Requests\Api\V1\TitleArtworkRequest;
use App\Models\Profile;
use App\Services\TitleArtworks;
use App\Services\Tmdb\TmdbCatalog;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * « Affiches, arrière-plans, logos » of a title, chosen by a profile (docs/images-des-titres.md).
 *
 * @tags Catalogue
 */
class TitleArtworkController extends Controller
{
    public function __construct(
        private readonly TmdbCatalog $catalog,
        private readonly TitleArtworks $artworks,
    ) {}

    /**
     * Images of a title.
     *
     * The posters, backdrops and logos of TMDb (French, English and without text, the best rated
     * first, 60 of each at most), TMDb's choice (`default`) and the active profile's (`chosen`,
     * null for each kind it left to TMDb). 
     */
    public function images(Request $request, MediaType $type, int $id): JsonResponse
    {
        $profile = ResolveProfile::profile($request);
        $title = $this->title($type, $id);
        $offered = $this->artworks->images($title);
        $logo = F::logo($title);

        return response()->json([
            'data' => [
                /** What TMDb gives (paths), shown by « Par défaut ». */
                'default' => [
                    'poster' => F::string($title, 'poster_path'),
                    'poster_url' => F::image(F::string($title, 'poster_path'), 'w342'),
                    'backdrop' => F::string($title, 'backdrop_path'),
                    'backdrop_url' => F::image(F::string($title, 'backdrop_path'), 'w780'),
                    'logo' => isset($logo['path']) ? (string) $logo['path'] : null,
                    'logo_url' => F::image(isset($logo['path']) ? (string) $logo['path'] : null, 'w300'),
                ],
                /** The active profile's choice (paths); null for a kind left to TMDb. */
                'chosen' => $profile === null ? null : $this->artworks->of($profile, $type, $id),
                'posters' => $offered['poster'],
                'backdrops' => $offered['backdrop'],
                'logos' => $offered['logo'],
            ],
        ]);
    }

    /**
     * Choose the images of a title.
     *
     * The kinds given change (a `path` of `catalog/{type}/{id}/images`, or null for TMDb's), the
     * others stay. From then on, every list, search and title page gives these images to this
     * profile. 422 on an image that isn't one of the title's.
     */
    public function update(TitleArtworkRequest $request, Profile $profile, MediaType $type, int $id): JsonResponse
    {
        Gate::authorize('update', $profile);
        $title = $this->title($type, $id);

        return response()->json([
            'data' => $this->artworks->choose(
                $profile,
                $type,
                $id,
                $request->safe()->only(array_keys(TitleArtworks::KINDS)),
                $this->artworks->images($title),
            ),
        ]);
    }

    /** @return array<string, mixed> */
    private function title(MediaType $type, int $id): array
    {
        return $type === MediaType::Movie ? $this->catalog->movie($id) : $this->catalog->show($id);
    }
}
