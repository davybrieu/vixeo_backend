<?php

namespace App\Services\Tmdb;

use App\Enums\MediaType;

/** A movie, a TV show or an episode, as TMDb rates it (docs/compte-tmdb.md). */
final readonly class RatingTarget
{
    private function __construct(public string $path) {}

    public static function title(MediaType $type, int $id): self
    {
        return new self("{$type->value}/{$id}");
    }

    public static function episode(int $showId, int $season, int $episode): self
    {
        return new self("tv/{$showId}/season/{$season}/episode/{$episode}");
    }
}
