<?php

namespace App\Enums;

enum SearchType: string
{
    case Movie = 'movie';
    case Tv = 'tv';
    case Person = 'person';
}
