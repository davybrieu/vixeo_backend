<?php

namespace App\Enums;

enum DiscoverSort: string
{
    case Popularity = 'popularity';
    case Rating = 'rating';
    case Recent = 'recent';
    case Oldest = 'oldest';

    public function sortBy(MediaType $type): string
    {
        $date = $type === MediaType::Movie ? 'primary_release_date' : 'first_air_date';

        return match ($this) {
            self::Popularity => 'popularity.desc',
            self::Rating => 'vote_average.desc',
            self::Recent => "{$date}.desc",
            self::Oldest => "{$date}.asc",
        };
    }
}
