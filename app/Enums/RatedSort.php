<?php

namespace App\Enums;

/** Order of "Mes avis" (GET /profiles/{profile}/tmdb/ratings). */
enum RatedSort: string
{
    /** Most recently rated first (default). */
    case Recent = 'recent';
    /** Least recently rated first. */
    case Oldest = 'oldest';
    /** Best rated first (then the most recent). */
    case Highest = 'highest';
    /** Worst rated first (then the most recent). */
    case Lowest = 'lowest';
}
