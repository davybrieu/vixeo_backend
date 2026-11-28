<?php

namespace App\Enums;

/** Order of a person's titles (GET /catalog/person/{id}/credits). */
enum PersonCreditsSort: string
{
    case Movie = 'movie';
    case Tv = 'tv';
}
