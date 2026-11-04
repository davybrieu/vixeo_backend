<?php

namespace App\Enums;

enum LibrarySort: string
{
    /** Most recently added first. */
    case Recent = 'recent';
    /** A → Z. */
    case Title = 'title';
    /** Most recent year first. */
    case Year = 'year';
}
