<?php

namespace App\Enums;

/** Order of a person's titles (GET /catalog/person/{id}/credits). */
enum PersonCreditsSort: string
{
    /** Most voted first: « Connu pour ». */
    case Popular = 'popular';
    /** Newest first: « Filmographie ». */
    case Date = 'date';
}
