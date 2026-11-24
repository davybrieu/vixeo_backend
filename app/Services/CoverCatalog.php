<?php

namespace App\Services;

/**
 * Profile covers (the banner of a profile page), grouped by style (resources/covers/catalog.json):
 * drawn by the apps from a pattern and colors. Edit that file to add styles or covers; never
 * rename or remove an id in use.
 */
class CoverCatalog
{
    /** @var list<array<string, mixed>>|null */
    private ?array $styles = null;

    /**
     * Styles and their covers, in the order of the file.
     *
     * @return list<array<string, mixed>>
     */
    public function styles(): array
    {
        if ($this->styles === null) {
            $data = json_decode((string) file_get_contents(resource_path('covers/catalog.json')), true, flags: JSON_THROW_ON_ERROR);
            $this->styles = array_values((array) ($data['styles'] ?? []));
        }

        return $this->styles;
    }

    /** @return list<string> */
    public function ids(): array
    {
        $ids = [];
        foreach ($this->styles() as $style) {
            foreach ((array) ($style['covers'] ?? []) as $cover) {
                $ids[] = (string) $cover['id'];
            }
        }

        return $ids;
    }

    /** A cover of the catalog, at random: the default of a new profile (null if the catalog is empty). */
    public function randomId(): ?string
    {
        $ids = $this->ids();

        return $ids === [] ? null : $ids[array_rand($ids)];
    }
}
