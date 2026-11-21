<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Requests\Api\V1\CatalogPageRequest;
use App\Http\Requests\Api\V1\PersonCreditsRequest;
use App\Http\Resources\V1\Catalog\CreditsResource;
use App\Http\Resources\V1\Catalog\MediaItemResource;
use App\Http\Resources\V1\Catalog\MovieResource;
use App\Http\Resources\V1\Catalog\PersonResource;
use App\Http\Resources\V1\Catalog\SeasonResource;
use App\Http\Resources\V1\Catalog\ShowResource;
use App\Services\Imdb\ImdbRatings;
use App\Services\Tmdb\AgeGate;
use App\Services\Tmdb\TitleSummaries;
use App\Services\Tmdb\TmdbCatalog;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Catalogue
 */
class CatalogTitleController extends Controller
{
    public function __construct(
        private TmdbCatalog $catalog,
        private AgeGate $ageGate,
        private TitleSummaries $summaries,
        private ImdbRatings $imdb,
    ) {}

    /**
     * Movie.
     *
     * Details, cast, trailer, age rating and recommendations.
     * 403 if the active profile may not see it.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function movie(Request $request, int $id): MovieResource
    {
        return new MovieResource($this->imdb->attach(MediaType::Movie, $id, $this->restricted($request, $this->catalog->movie($id))));
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
        return new ShowResource($this->imdb->attach(MediaType::Tv, $id, $this->restricted($request, $this->catalog->show($id))));
    }

    /**
     * Recommendations of a movie / TV show.
     *
     * Every page of « Vous pourriez aussi aimer » (the title page shows the first 20): TMDb
     * recommendations, or similar titles when TMDb has none. Filtered by the active profile's
     * age limit; 403 if the profile may not see the title itself.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function recommendations(CatalogPageRequest $request, MediaType $type, int $id): JsonResponse
    {
        $profile = ResolveProfile::profile($request);
        $this->ageGate->authorizeTitle($profile, $this->title($type, $id));

        $page = $this->catalog->recommendations($type, $id, $request->page());
        // Logo, summary and IMDb rating with each title, like the title page (TitleSummaries).
        $page['results'] = $this->summaries->add($this->ageGate->filter($profile, $page['results']));

        return response()->json([
            'data' => MediaItemResource::collection($page['results']),
            'meta' => [
                'page' => (int) $page['page'],
                'total_pages' => (int) $page['total_pages'],
                'total_results' => (int) $page['total_results'],
            ],
        ]);
    }

    /**
     * Credits of a movie / TV show.
     *
     * Everyone of « Créateur et distribution » (the title page shows 20 cast members): directors
     * (movie) or creators (TV show), then the whole cast. 403 if the active profile may not see
     * the title.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function credits(Request $request, MediaType $type, int $id): CreditsResource
    {
        $title = $this->title($type, $id);
        $this->ageGate->authorizeTitle(ResolveProfile::profile($request), $title);

        return new CreditsResource($title, $type);
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

        $data = $this->catalog->season($id, $season);
        // Each episode's IMDb rating (docs/notes.md).
        $data['episodes'] = $this->imdb->attachToEpisodes($id, $season, array_values((array) ($data['episodes'] ?? [])));

        return new SeasonResource($data);
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
        // IMDb rating with each title: with the summary for the 10 of « Connu pour », alone for the
        // 100 of the filmography (TitleSummaries::ratings).
        $lists['known_for'] = $this->summaries->add($lists['known_for']);
        $lists['filmography'] = $this->summaries->ratings($lists['filmography']);

        return new PersonResource(array_merge($person, $lists));
    }

    /**
     * Titles of a person.
     *
     * Every title of « Connu pour » (`sort=popular`, the person page shows 10) or « Filmographie »
     * (`sort=date`, newest first), each once, filtered by the active profile's age limit.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function personCredits(PersonCreditsRequest $request, int $id): JsonResponse
    {
        // Every title (often more than a hundred): the IMDb rating alone (TitleSummaries::ratings).
        $titles = $this->summaries->ratings($this->ageGate->filter(
            ResolveProfile::profile($request),
            $this->catalog->personCredits($id, $request->sort()),
        ));

        return response()->json(['data' => MediaItemResource::collection($titles)]);
    }

    /**
     * TMDb data of a movie or TV show page (cached).
     *
     * @return array<string, mixed>
     */
    private function title(MediaType $type, int $id): array
    {
        return $type === MediaType::Movie ? $this->catalog->movie($id) : $this->catalog->show($id);
    }

    /**
     * @param  array<string, mixed>  $title
     * @return array<string, mixed>
     */
    private function restricted(Request $request, array $title): array
    {
        $profile = ResolveProfile::profile($request);

        $this->ageGate->authorizeTitle($profile, $title);
        // "Vous pourriez aussi aimer" cards show the title logo like the home rows: logo, textless
        // poster and summary come with each title (TitleSummaries, cached a day, one batch).
        $title['recommended'] = $this->summaries->add($this->ageGate->filter($profile, $title['recommended']));

        return $title;
    }
}
