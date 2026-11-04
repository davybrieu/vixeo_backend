<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Catalog\MovieResource;
use App\Http\Resources\V1\Catalog\PersonResource;
use App\Http\Resources\V1\Catalog\SeasonResource;
use App\Http\Resources\V1\Catalog\ShowResource;
use App\Services\Tmdb\TmdbCatalog;

/**
 * @tags Catalogue
 */
class CatalogTitleController extends Controller
{
    public function __construct(private TmdbCatalog $catalog) {}

    /**
     * Movie.
     *
     * Details, cast, trailer, age rating and recommendations.
     */
    public function movie(int $id): MovieResource
    {
        return new MovieResource($this->catalog->movie($id));
    }

    /**
     * TV show.
     *
     * Details, seasons, cast, trailer, age rating and recommendations.
     */
    public function show(int $id): ShowResource
    {
        return new ShowResource($this->catalog->show($id));
    }

    /**
     * Season with its episodes.
     */
    public function season(int $id, int $season): SeasonResource
    {
        return new SeasonResource($this->catalog->season($id, $season));
    }

    /**
     * Person.
     *
     * Biography and filmography.
     */
    public function person(int $id): PersonResource
    {
        return new PersonResource($this->catalog->person($id));
    }
}
