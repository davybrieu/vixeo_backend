<?php

namespace App\Services\Tmdb;

/**
 * Compatibility shim for existing catalog services. Vixeo no longer filters or blocks content
 * based on age classifications; every account has the same unrestricted catalog.
 */
class AgeGate
{
    /** @param array<string, mixed> $title */
    public function authorizeTitle(?\App\Models\Profile $profile, array $title): void
    {
        // No age restrictions.
    }

    /** @param array<string, mixed> $title */
    public function allows(?\App\Models\Profile $profile, array $title): bool
    {
        return true;
    }

    public function authorizeShow(?\App\Models\Profile $profile, int $showId): void
    {
        // No age restrictions.
    }

    /** @param list<array<string, mixed>> $items
     *  @return list<array<string, mixed>>
     */
    public function filter(?\App\Models\Profile $profile, array $items): array
    {
        return $items;
    }

    /** @param array<array-key, list<array<string, mixed>>> $lists
     *  @return array<array-key, list<array<string, mixed>>>
     */
    public function filterMany(?\App\Models\Profile $profile, array $lists): array
    {
        return $lists;
    }
}
