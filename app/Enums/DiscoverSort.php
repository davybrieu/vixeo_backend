<?php

namespace App\Enums;

enum DiscoverSort: string
{
    case Popularity = 'popularity';
    case Rating = 'rating';
    case Recent = 'recent';

    public function sortBy(MediaType $type): string
    {
        return match ($this) {
            self::Popularity => 'popularity.desc',
            self::Rating => 'vote_average.desc',
            self::Recent => $type === MediaType::Movie ? 'primary_release_date.desc' : 'first_air_date.desc',
        };
    }
}
