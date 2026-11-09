<?php

namespace App\Services;

/**
 * Profile avatars, grouped by license (resources/avatars/catalog.json).
 * Edit that file to add licenses or avatars; never rename or remove an id in use.
 */
class AvatarCatalog
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $licenses = null;

    /**
     * Licenses sorted by name, their avatars sorted by name.
     *
     * @return list<array<string, mixed>>
     */
    public function licenses(): array
    {
        if ($this->licenses === null) {
            $data = json_decode((string) file_get_contents(resource_path('avatars/catalog.json')), true, flags: JSON_THROW_ON_ERROR);
            $licenses = $data['licenses'] ?? [];
            // French alphabetical order ("Élan" with the E's).
            $collator = new \Collator('fr_FR');
            $byName = fn (array $a, array $b) => $collator->compare($a['name'], $b['name']);

            foreach ($licenses as &$license) {
                usort($license['avatars'], $byName);
            }
            unset($license);

            usort($licenses, $byName);
            $this->licenses = $licenses;
        }

        return array_values($this->licenses);
    }

    /** @return list<string> */
    public function ids(): array
    {
        $ids = [];
        foreach ($this->licenses() as $license) {
            foreach ($license['avatars'] as $avatar) {
                $ids[] = (string) $avatar['id'];
            }
        }

        return $ids;
    }

    /** An avatar of the catalog, at random: the default of a new profile (null if the catalog is empty). */
    public function randomId(): ?string
    {
        $ids = $this->ids();

        return $ids === [] ? null : $ids[array_rand($ids)];
    }
}
