<?php

namespace App\Enums;

/** How a profile wants catalog titles drawn in rows and grids. */
enum PosterFormat: string
{
    /** Vertical poster (2:3). Default. */
    case Portrait = 'portrait';
    /** Horizontal image (16:9). */
    case Landscape = 'landscape';
}
