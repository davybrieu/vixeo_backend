<?php

namespace App\Enums;

/** Kind of content a profile item points to (any source: TMDb, Jellyfin, IPTV, radio…). */
enum ItemType: string
{
    case Movie = 'movie';
    /** A whole TV show (e.g. favorite series). */
    case Tv = 'tv';
    case Episode = 'episode';
    /** Live TV channel. */
    case Channel = 'channel';
    case Radio = 'radio';
    /** Any other video (local file, replay…). */
    case Video = 'video';
}
