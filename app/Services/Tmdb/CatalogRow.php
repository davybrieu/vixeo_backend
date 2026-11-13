<?php

namespace App\Services\Tmdb;

use App\Enums\MediaType;

/**
 * A home row that is not one of TMDb's curated lists (App\Enums\CatalogList): the trends of the
 * day, a streaming platform, a TV network, a studio, a language. Asked to TMDb at `$path` with
 * `$query` (most of them through "discover"); `$list` names it in the API, like a curated list:
 * `GET /catalog/{type}/lists/{list}` is its "see all" page.
 */
final readonly class CatalogRow
{
    /**
     * @param  string  $title  Display title, already translated.
     * @param  array<string, scalar>  $query
     */
    public function __construct(
        public MediaType $type,
        public string $list,
        public string $title,
        public string $path,
        public array $query = [],
    ) {}

    /** Key of the row in the home (stable: the apps sort and hide rows by key). */
    public function key(): string
    {
        return "{$this->type->value}_{$this->list}";
    }
}
