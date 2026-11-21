<?php

namespace App\Enums;

/** Order of text search results (TMDb only gives them by relevance: the API sorts them). */
enum SearchSort: string
{
    /** TMDb order for the typed text. */
    case Relevance = 'relevance';
    case Popularity = 'popularity';
    /** Best rated first: IMDb's rating, else TMDb's (TitleFilters::rating); unrated titles last. */
    case Rating = 'rating';
    case Recent = 'recent';
    case Oldest = 'oldest';
}
