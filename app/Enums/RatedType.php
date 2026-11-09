<?php

namespace App\Enums;

/** What a profile rates on TMDb (docs/compte-tmdb.md). */
enum RatedType: string
{
    case Movie = 'movie';
    case Tv = 'tv';
    case Episode = 'episode';

    /** TMDb list of the account's ratings of this type. */
    public function ratedPath(int $accountId): string
    {
        return match ($this) {
            self::Movie => "account/{$accountId}/rated/movies",
            self::Tv => "account/{$accountId}/rated/tv",
            self::Episode => "account/{$accountId}/rated/tv/episodes",
        };
    }
}
