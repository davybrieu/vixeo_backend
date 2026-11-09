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
}
