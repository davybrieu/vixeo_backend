<?php

namespace App\Enums;

/** Order of a person's titles (GET /catalog/person/{id}/credits). */
enum PersonCreditsType: string
{
    case Movie = 'movie';
    case Tv = 'tv';
}
