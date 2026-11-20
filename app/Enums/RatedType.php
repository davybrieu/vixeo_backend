<?php

namespace App\Enums;

/** What a profile reviews (docs/avis.md): movies and TV shows, never episodes. */
enum RatedType: string
{
    case Movie = 'movie';
    case Tv = 'tv';
}
