<?php

namespace App\Enums;

/**
 * Curated TMDb lists, used as home rows and "see all" pages.
 */
enum CatalogList: string
{
    case Trending = 'trending';
    case Popular = 'popular';
    case TopRated = 'top_rated';
    case NowPlaying = 'now_playing';
    case Upcoming = 'upcoming';
    case OnTheAir = 'on_the_air';
    case AiringToday = 'airing_today';

    public function supports(MediaType $type): bool
    {
        return match ($this) {
            self::NowPlaying, self::Upcoming => $type === MediaType::Movie,
            self::OnTheAir, self::AiringToday => $type === MediaType::Tv,
            default => true,
        };
    }

    /** TMDb endpoint path for this list. */
    public function path(MediaType $type): string
    {
        return $this === self::Trending
            ? "trending/{$type->value}/week"
            : "{$type->value}/{$this->value}";
    }

    public function title(MediaType $type): string
    {
        $movie = $type === MediaType::Movie;

        return __(match ($this) {
            self::Trending => $movie ? 'Trending movies' : 'Trending TV shows',
            self::Popular => $movie ? 'Popular movies' : 'Popular TV shows',
            self::TopRated => $movie ? 'Top rated movies' : 'Top rated TV shows',
            self::NowPlaying => 'Now playing in theaters',
            self::Upcoming => 'Upcoming movies',
            self::OnTheAir => 'Currently airing',
            self::AiringToday => 'Airing today',
        });
    }

    /** @return list<self> */
    public static function for(MediaType $type): array
    {
        return array_values(array_filter(self::cases(), fn (self $list) => $list->supports($type)));
    }
}
