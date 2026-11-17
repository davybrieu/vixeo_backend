<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Addons\VixeoCatalogAddon;
use Illuminate\Http\JsonResponse;

/**
 * The Vixeo catalog as a plug-in in the Stremio add-on format (docs/plugins.md): manifest,
 * catalogs and metadata, readable by any app that installs Stremio add-ons. Public, without an
 * account: those apps send no token. Any website may read it (`Access-Control-Allow-Origin: *`).
 *
 * @unauthenticated
 */
class VixeoAddonController extends Controller
{
    public function __construct(private VixeoCatalogAddon $addon) {}

    /**
     * The manifest: what the plug-in serves (catalogs, metadata of movies and series).
     *
     * @response array{id: string, version: string, name: string, description: string, logo: string, resources: list<string>, types: list<string>, idPrefixes: list<string>, catalogs: list<array{type: string, id: string, name: string, extra: list<array{name: string, isRequired?: bool}>}>, behaviorHints: array{adult: bool, p2p: bool, configurable: bool, configurationRequired: bool}}
     */
    public function manifest(): JsonResponse
    {
        return $this->json($this->addon->manifest(), 3600);
    }

    /**
     * A catalog: `{metas: [...]}`.
     *
     * @response array{metas: list<array{id: string, type: string, name: string, poster?: string, posterShape?: string, background?: string, description?: string, releaseInfo?: string, imdbRating?: string}>}
     */
    public function catalog(string $type, string $catalog): JsonResponse
    {
        return $this->json($this->addon->catalog($type, $catalog), 1800);
    }

    /**
     * A catalog page or a search: extra properties in the path, as Stremio sends them
     * (`skip=20`, `search=dune`, joined by `&`).
     *
     * @response array{metas: list<array{id: string, type: string, name: string, poster?: string, posterShape?: string, background?: string, description?: string, releaseInfo?: string, imdbRating?: string}>}
     */
    public function catalogWithExtra(string $type, string $catalog, string $extra): JsonResponse
    {
        parse_str($extra, $values);

        return $this->json($this->addon->catalog($type, $catalog, array_map(
            fn ($value) => is_scalar($value) ? (string) $value : '',
            array_filter($values, fn ($value, $key) => is_string($key), ARRAY_FILTER_USE_BOTH),
        )), 1800);
    }

    /**
     * The details of a movie or a series (`tmdb:550`), with its episodes: `{meta: {...}}`.
     *
     * @response array{meta: array{id: string, type: string, name: string, poster?: string, background?: string, logo?: string, description?: string, releaseInfo?: string, released?: string, runtime?: string, imdbRating?: string, genres: list<string>, cast: list<string>, director?: list<string>, imdb_id?: string, trailers: list<array{source: string, type: string}>, videos?: list<array{id: string, title: string, season: int, episode: int, released?: string, overview?: string, thumbnail?: string}>}}
     */
    public function meta(string $type, string $id): JsonResponse
    {
        $meta = $this->addon->meta($type, $id);
        abort_if($meta === null, 404);

        return $this->json($meta, 3600);
    }

    /** @param  array<string, mixed>  $data */
    private function json(array $data, int $maxAge): JsonResponse
    {
        return response()->json($data, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => "public, max-age={$maxAge}",
        ]);
    }
}
