<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Resources\V1\Catalog\MovieResource;
use App\Http\Resources\V1\Catalog\PersonResource;
use App\Http\Resources\V1\Catalog\SeasonResource;
use App\Http\Resources\V1\Catalog\ShowResource;
use App\Services\Tmdb\AgeGate;
use App\Services\Tmdb\TmdbCatalog;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\Request;

/**
 * @tags Catalogue
 */
class CatalogTitleController extends Controller
{
    public function __construct(private TmdbCatalog $catalog, private AgeGate $ageGate) {}

    /**
     * Movie.
     *
     * Details, cast, trailer, age rating and recommendations.
     * 403 if the active profile may not see it.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function movie(Request $request, int $id): MovieResource
    {
        return new MovieResource($this->restricted($request, $this->catalog->movie($id)));
    }

    /**
     * TV show.
     *
     * Details, seasons, cast, trailer, age rating and recommendations.
     * 403 if the active profile may not see it.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function show(Request $request, int $id): ShowResource
    {
        return new ShowResource($this->restricted($request, $this->catalog->show($id)));
    }

    /**
     * Season with its episodes.
     *
     * 403 if the active profile may not see the TV show.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function season(Request $request, int $id, int $season): SeasonResource
    {
        $this->ageGate->authorizeShow(ResolveProfile::profile($request), $id);

        return new SeasonResource($this->catalog->season($id, $season));
    }

    /**
     * Person.
     *
     * Biography and filmography (filtered by the active profile's age limit).
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function person(Request $request, int $id): PersonResource
    {
        $person = $this->catalog->person($id);

        $lists = $this->ageGate->filterMany(ResolveProfile::profile($request), [
            'known_for' => $person['known_for'],
            'filmography' => $person['filmography'],
        ]);

        return new PersonResource(array_merge($person, $lists));
    }

    /**
     * @param  array<string, mixed>  $title
     * @return array<string, mixed>
     */
    private function restricted(Request $request, array $title): array
    {
        $profile = ResolveProfile::profile($request);

        $this->ageGate->authorizeTitle($profile, $title);
        $title['recommended'] = $this->ageGate->filter($profile, $title['recommended']);

        return $title;
    }
}
