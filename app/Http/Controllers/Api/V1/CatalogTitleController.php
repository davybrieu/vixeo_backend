<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Requests\Api\V1\CatalogPageRequest;
use App\Http\Requests\Api\V1\CreditsRequest;
use App\Http\Requests\Api\V1\PersonCreditsRequest;
use App\Http\Resources\V1\Catalog\CreditsResource;
use App\Http\Resources\V1\Catalog\MediaItemResource;
use App\Http\Resources\V1\Catalog\MovieResource;
use App\Http\Resources\V1\Catalog\PersonResource;
use App\Http\Resources\V1\Catalog\SeasonResource;
use App\Http\Resources\V1\Catalog\ShowResource;
use App\Services\Imdb\ImdbRatings;
use App\Services\Tmdb\AgeGate;
use App\Services\Tmdb\TitleCrew;
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
     * « Distribution » (the title page shows 20 cast members): directors (movie) or creators (TV
     * show) on the first page, then the cast in billing order, 20 a page (the « Voir tout » page
     * loads the next one as it scrolls). `group=crew`: « Équipe technique », everyone but the
     * actors, once each with their jobs in French, 20 a page (`crew`). 403 if the active profile
     * may not see the title.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function credits(CreditsRequest $request, MediaType $type, int $id): JsonResponse
    {
        $title = $this->title($type, $id);
        $this->ageGate->authorizeTitle(ResolveProfile::profile($request), $title);
        $total = count($request->crew() ? TitleCrew::of($title, $type) : CreditsResource::cast($title, $type));

        return (new CreditsResource($title, $type, $request->page(), $request->crew()))->additional(['meta' => [
            'page' => $request->page(),
            'total_pages' => max(1, (int) ceil($total / CreditsResource::PAGE)),
            'total_results' => $total,
        ]])->response();
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
     * Biography, the 20 best-known titles (with their summary) and the 20 latest of the filmography
     * (with their IMDb rating), filtered by the active profile's age limit. The rest:
     * `GET /catalog/person/{id}/credits`, page by page.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function person(Request $request, int $id): PersonResource
    {
        $person = $this->catalog->person($id);

        $lists = $this->ageGate->filterMany(ResolveProfile::profile($request), [
            'known_for' => $person['known_for'],
            'filmography' => $person['filmography'],
        ]);
        // IMDb rating with each title: with the summary for « Connu pour », alone for the filmography,
        // in one batch of TMDb requests (TitleSummaries::addAndRate).
        [$lists['known_for'], $lists['filmography']] = $this->summaries->addAndRate($lists['known_for'], $lists['filmography']);

        return new PersonResource(array_merge($person, $lists));
    }

    /**
     * Titles of a person.
     *
     * Every title of « Connu pour » (`sort=popular`) or « Filmographie » (`sort=date`, newest first),
     * each once, 20 a page (the « Voir tout » pages load the next one as they scroll), filtered by
     * the active profile's age limit: a page can be shorter.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function personCredits(PersonCreditsRequest $request, int $id): JsonResponse
    {
        $all = $this->catalog->personCredits($id, $request->sort());
        $size = TmdbCatalog::PERSON_PAGE;
        $page = $request->page();
        // The IMDb rating alone (TitleSummaries::ratings: the IMDb id, light), for this page only.
        $titles = $this->summaries->ratings($this->ageGate->filter(
            ResolveProfile::profile($request),
            array_slice($all, ($page - 1) * $size, $size),
        ));

        return response()->json([
            'data' => MediaItemResource::collection($titles),
            'meta' => [
                'page' => $page,
                'total_pages' => max(1, (int) ceil(count($all) / $size)),
                'total_results' => count($all),
            ],
        ]);
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
