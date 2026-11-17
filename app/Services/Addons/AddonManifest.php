<?php

namespace App\Services\Addons;

use App\Enums\MediaType;

/**
 * The manifest of a plug-in, in the Stremio add-on format (docs/plugins.md): who it is
 * (`id`, `name`, `version`), what it serves (`resources`: catalog, meta, stream, subtitles), for
 * which types (`movie`, `series`…) and which ids (`idPrefixes`: `tt`, `tmdb:`…).
 *
 * Vixeo only uses the `stream` resource (sources of the player), for the `movie` and `series`
 * types: the catalogs of a plug-in are ignored, the catalog is TMDb's.
 */
final class AddonManifest
{
    /**
     * @param  list<string>  $types
     * @param  list<string>  $idPrefixes
     * @param  list<array{name: string, types: list<string>, idPrefixes: list<string>}>  $resources
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

    /** Whether it serves streams of movies or series: the only thing Vixeo asks plug-ins for. */
    public function servesStreams(): bool
    {
        return $this->supports('stream', 'movie') || $this->supports('stream', 'series');
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
