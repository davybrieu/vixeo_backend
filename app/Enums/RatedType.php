<?php

namespace App\Enums;

/** What a profile reviews (docs/avis.md). */
enum RatedType: string
{
    case Movie = 'movie';
    case Tv = 'tv';
    case Episode = 'episode';
}
