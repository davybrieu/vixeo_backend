<?php

namespace App\Enums;

/** Kind of content a profile item points to (any source: TMDb, Jellyfin / Plex, FAST channels…). */
enum ItemType: string
{
    case Movie = 'movie';
    /** A whole TV show (e.g. favorite series). */
    case Tv = 'tv';
    case Episode = 'episode';
    /** Live TV channel (FAST). */
    case Channel = 'channel';
    /** Any other video (local file…). */
    case Video = 'video';
}
