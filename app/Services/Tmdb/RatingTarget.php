<?php

namespace App\Services\Tmdb;

use App\Enums\MediaType;
use App\Enums\RatedType;

/** A movie, a TV show or an episode, as TMDb rates it (docs/compte-tmdb.md). */
final readonly class RatingTarget
{
    /** TMDb path, also the key of the profile's copy ("movie/550", "tv/1399/season/1/episode/2"). */
    public string $path;

    private function __construct(
        public RatedType $type,
        /** Movie or TV show (also for an episode). */
        public int $id,
        public ?int $season = null,
        public ?int $episode = null,
    ) {
        $this->path = $type === RatedType::Episode
            ? "tv/{$id}/season/{$season}/episode/{$episode}"
            : "{$type->value}/{$id}";
    }

    public static function title(MediaType $type, int $id): self
    {
        return new self($type === MediaType::Movie ? RatedType::Movie : RatedType::Tv, $id);
    }

    public static function episode(int $showId, int $season, int $episode): self
    {
        return new self(RatedType::Episode, $showId, $season, $episode);
    }

    /** The target of a path ("movie/550", "tv/1399/season/1/episode/2"). */
    public static function fromPath(string $path): self
    {
        $parts = explode('/', $path);

        return count($parts) === 6
            ? self::episode((int) $parts[1], (int) $parts[3], (int) $parts[5])
            : self::title($parts[0] === 'movie' ? MediaType::Movie : MediaType::Tv, (int) ($parts[1] ?? 0));
    }
}
