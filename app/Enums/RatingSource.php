<?php

namespace App\Enums;

/** Site whose rating a profile wants on titles (docs/notes.md): MDBList's sources. */
enum RatingSource: string
{
    /** IMDb, out of 10. Default. */
    case Imdb = 'imdb';
    /** TMDb, out of 10 (also given with every title, without MDBList). */
    case Tmdb = 'tmdb';
    /** Rotten Tomatoes critics (Tomatometer), percentage. */
    case Tomatoes = 'tomatoes';
    /** Rotten Tomatoes audience (Popcornmeter), percentage. */
    case Audience = 'audience';
    /** Metacritic, out of 100. */
    case Metacritic = 'metacritic';
    /** Letterboxd, out of 5 (movies). */
    case Letterboxd = 'letterboxd';
    /** Trakt, out of 100. */
    case Trakt = 'trakt';
    /** MyAnimeList, out of 10 (anime). */
    case Mal = 'mal';
}
