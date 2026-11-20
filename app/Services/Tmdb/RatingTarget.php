<?php

namespace App\Services\Tmdb;

use App\Enums\MediaType;
use App\Enums\RatedType;

/** A reviewed movie or TV show (docs/avis.md). Episodes are not rated. */
final readonly class RatingTarget
{
    /** TMDb path of the title, also the key of the review ("movie/550", "tv/1399"). */
    public string $path;

    private function __construct(
        public RatedType $type,
        public int $id,
    ) {
        $this->path = "{$type->value}/{$id}";
    }

    public static function title(MediaType $type, int $id): self
    {
        return new self($type === MediaType::Movie ? RatedType::Movie : RatedType::Tv, $id);
    }

    /** The target of a path ("movie/550", "tv/1399"). */
    public static function fromPath(string $path): self
    {
        $parts = explode('/', $path);

        return self::title($parts[0] === 'movie' ? MediaType::Movie : MediaType::Tv, (int) ($parts[1] ?? 0));
    }
}
