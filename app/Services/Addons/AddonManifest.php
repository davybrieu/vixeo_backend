<?php

namespace App\Services\Addons;

use App\Enums\MediaType;

/**
 * The manifest of a plug-in, in the Stremio add-on format (docs/plugins.md): who it is
 * (`id`, `name`, `version`), what it serves (`resources`: catalog, meta, stream, subtitles), for
 * which types (`movie`, `series`…) and which ids (`idPrefixes`: `tt`, `tmdb:`…), and its
 * catalogs.
 *
 * Vixeo only uses the `movie` and `series` types.
 */
final class AddonManifest
{
    /**
     * @param  list<string>  $types
     * @param  list<string>  $idPrefixes
     * @param  list<array{name: string, types: list<string>, idPrefixes: list<string>}>  $resources
     * @param  list<array{type: string, id: string, name: string, required: list<string>, skip: bool, search: bool, genre: bool}>  $catalogs
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly ?string $description,
        public readonly ?string $logo,
        public readonly array $types,
        public readonly array $idPrefixes,
        public readonly array $resources,
        public readonly array $catalogs,
        public readonly bool $adult,
        public readonly bool $configurationRequired,
        public readonly array $raw,
    ) {}

    /**
     * Reads a manifest. Throws InvalidAddon when it is not one.
     *
     * @param  array<mixed>  $manifest
     */
    public static function fromArray(array $manifest): self
    {
        $id = self::text($manifest['id'] ?? null);
        $name = self::text($manifest['name'] ?? null);
        $version = self::text($manifest['version'] ?? null);
        if ($id === null || $name === null || $version === null
            || ! is_array($manifest['resources'] ?? null) || ! is_array($manifest['types'] ?? null)) {
            throw new InvalidAddon(__('This address is not a plug-in.'));
        }

        $types = self::texts($manifest['types']);
        $idPrefixes = self::texts($manifest['idPrefixes'] ?? []);

        $resources = [];
        foreach ($manifest['resources'] as $resource) {
            if (is_string($resource) && $resource !== '') {
                $resources[] = ['name' => $resource, 'types' => $types, 'idPrefixes' => $idPrefixes];
            } elseif (is_array($resource) && self::text($resource['name'] ?? null) !== null) {
                $resources[] = [
                    'name' => (string) $resource['name'],
                    'types' => isset($resource['types']) ? self::texts($resource['types']) : $types,
                    'idPrefixes' => isset($resource['idPrefixes']) ? self::texts($resource['idPrefixes']) : $idPrefixes,
                ];
            }
        }

        $catalogs = [];
        foreach (is_array($manifest['catalogs'] ?? null) ? $manifest['catalogs'] : [] as $catalog) {
            $type = is_array($catalog) ? self::text($catalog['type'] ?? null) : null;
            $catalogId = is_array($catalog) ? self::text($catalog['id'] ?? null) : null;
            if ($type === null || $catalogId === null) {
                continue;
            }
            // Extra properties: `extra` (current), `extraSupported` / `extraRequired` (older add-ons).
            $supported = [];
            $required = [];
            foreach (is_array($catalog['extra'] ?? null) ? $catalog['extra'] : [] as $extra) {
                $extraName = is_array($extra) ? self::text($extra['name'] ?? null) : null;
                if ($extraName !== null) {
                    $supported[] = $extraName;
                    if (! empty($extra['isRequired'])) {
                        $required[] = $extraName;
                    }
                }
            }
            $supported = array_values(array_unique([...$supported, ...self::texts($catalog['extraSupported'] ?? [])]));
            $required = array_values(array_unique([...$required, ...self::texts($catalog['extraRequired'] ?? [])]));

            $catalogs[] = [
                'type' => $type,
                'id' => $catalogId,
                'name' => self::text($catalog['name'] ?? null) ?? $catalogId,
                'required' => $required,
                'skip' => in_array('skip', $supported, true),
                'search' => in_array('search', $supported, true),
                'genre' => in_array('genre', $supported, true),
            ];
        }

        $hints = is_array($manifest['behaviorHints'] ?? null) ? $manifest['behaviorHints'] : [];

        return new self(
            id: $id,
            name: $name,
            version: $version,
            description: self::text($manifest['description'] ?? null),
            logo: self::httpsUrl($manifest['logo'] ?? null),
            types: $types,
            idPrefixes: $idPrefixes,
            resources: $resources,
            catalogs: $catalogs,
            adult: ! empty($hints['adult']),
            configurationRequired: ! empty($hints['configurationRequired']),
            raw: $manifest,
        );
    }

    /** The Stremio type of a Vixeo media type. */
    public static function stremioType(MediaType $type): string
    {
        return $type === MediaType::Movie ? 'movie' : 'series';
    }

    /** The Vixeo media type of a Stremio type; null for the types Vixeo does not show (tv, channel…). */
    public static function mediaType(string $type): ?MediaType
    {
        return match ($type) {
            'movie' => MediaType::Movie,
            'series' => MediaType::Tv,
            default => null,
        };
    }

    /**
     * What a person types or pastes, as the manifest's address: `stremio://` becomes `https://`,
     * and `/manifest.json` is added when missing.
     */
    public static function normalizeUrl(string $input): string
    {
        $url = trim($input);
        if (str_starts_with(strtolower($url), 'stremio://')) {
            $url = 'https://'.substr($url, strlen('stremio://'));
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (! str_ends_with($path, '/manifest.json')) {
            $query = parse_url($url, PHP_URL_QUERY);
            $base = $query === null || $query === false ? $url : substr($url, 0, -strlen('?'.$query));
            $url = rtrim($base, '/').'/manifest.json'.($query ? '?'.$query : '');
        }

        return $url;
    }

    /** The address resources are read under: the manifest's, without `/manifest.json`. */
    public static function baseUrl(string $manifestUrl): string
    {
        $path = (string) parse_url($manifestUrl, PHP_URL_PATH);
        $position = strrpos($manifestUrl, '/manifest.json');
        if ($position === false || ! str_ends_with($path, '/manifest.json')) {
            return rtrim($manifestUrl, '/');
        }

        return substr($manifestUrl, 0, $position);
    }

    /** Whether the plug-in serves `$resource` for `$type` (and for `$id`, when given). */
    public function supports(string $resource, string $type, ?string $id = null): bool
    {
        foreach ($this->resources as $entry) {
            if ($entry['name'] !== $resource || ! in_array($type, $entry['types'], true)) {
                continue;
            }
            if ($id === null || $entry['idPrefixes'] === []) {
                return true;
            }
            foreach ($entry['idPrefixes'] as $prefix) {
                if (str_starts_with($id, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Whether it serves something Vixeo shows: a catalog or streams of movies or series. */
    public function isUseful(): bool
    {
        foreach (['movie', 'series'] as $type) {
            if ($this->supports('stream', $type) || $this->catalogsFor($type) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Catalogs of a type that can be shown as they are (no required extra, like a search).
     *
     * @return list<array{type: string, id: string, name: string, required: list<string>, skip: bool, search: bool, genre: bool}>
     */
    public function catalogsFor(string $type): array
    {
        if (! $this->supports('catalog', $type)) {
            return [];
        }

        return array_values(array_filter(
            $this->catalogs,
            fn (array $catalog) => $catalog['type'] === $type && $catalog['required'] === [],
        ));
    }

    /** @return array{type: string, id: string, name: string, required: list<string>, skip: bool, search: bool, genre: bool}|null */
    public function catalog(string $type, string $id): ?array
    {
        foreach ($this->catalogsFor($type) as $catalog) {
            if ($catalog['id'] === $id) {
                return $catalog;
            }
        }

        return null;
    }

    /**
     * Resources, in the API's words ("catalog", "meta", "stream", "subtitles"), for the types
     * Vixeo shows.
     *
     * @return list<string>
     */
    public function resourceNames(): array
    {
        $names = [];
        foreach ($this->resources as $entry) {
            if (array_intersect($entry['types'], ['movie', 'series']) !== []) {
                $names[] = $entry['name'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Vixeo's types it serves ("movie", "tv").
     *
     * @return list<string>
     */
    public function mediaTypes(): array
    {
        $types = [];
        foreach (['movie' => 'movie', 'series' => 'tv'] as $stremio => $vixeo) {
            if (in_array($stremio, $this->types, true)
                || array_filter($this->resources, fn (array $r) => in_array($stremio, $r['types'], true)) !== []) {
                $types[] = $vixeo;
            }
        }

        return $types;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @return list<string> */
    private static function texts(mixed $values): array
    {
        return is_array($values)
            ? array_values(array_filter(array_map(fn ($v) => self::text($v), $values), fn ($v) => $v !== null))
            : [];
    }

    private static function httpsUrl(mixed $value): ?string
    {
        $url = self::text($value);

        return $url !== null && str_starts_with(strtolower($url), 'https://') ? $url : null;
    }
}
