<?php

namespace App\Http\Resources\V1\Addons;

use App\Models\Addon;
use App\Services\Addons\AddonManifest;
use App\Services\Addons\VixeoCatalogAddon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plug-in of the account (docs/plugins.md). The address of a plug-in is never sent back
 * whole (it may hold a personal key): only its host.
 *
 * @property array{key: string, manifest: AddonManifest, addon: Addon|null} $resource
 */
class AddonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AddonManifest $manifest */
        $manifest = $this->resource['manifest'];
        /** @var Addon|null $addon */
        $addon = $this->resource['addon'];
        $builtIn = $addon === null;

        return [
            /** "vixeo" for the Vixeo catalog, otherwise the plug-in's number. */
            'id' => (string) $this->resource['key'],
            'name' => (string) $manifest->name,
            'description' => $manifest->description === null ? null : (string) $manifest->description,
            'version' => (string) $manifest->version,
            /** Logo (https only), null when the plug-in has none. */
            'logo_url' => $manifest->logo === null ? null : (string) $manifest->logo,
            /** Host of the plug-in's address ("addon.example.com"); null for the Vixeo catalog. */
            'host' => $builtIn ? null : (string) $addon->host(),
            /**
             * What it serves: "catalog", "meta", "stream", "subtitles".
             *
             * @var list<string>
             */
            'resources' => $manifest->resourceNames(),
            /**
             * "movie", "tv".
             *
             * @var list<string>
             */
            'types' => $manifest->mediaTypes(),
            /** Number of its catalogs shown as rows (movies and TV shows). */
            'catalogs' => count($manifest->catalogsFor('movie')) + count($manifest->catalogsFor('series')),
            'enabled' => $builtIn || $addon->enabled,
            /** The Vixeo catalog: built in, cannot be turned off nor removed. */
            'built_in' => $builtIn,
            /** Installed on the server for every account: the account cannot change it. */
            'shared' => ! $builtIn && $addon->user_id === null,
            /** For adults only (hidden from profiles with an age limit). */
            'adult' => (bool) $manifest->adult,
            /** The Stremio address of the Vixeo catalog, to add it to another app; null for the others. */
            'manifest_url' => $builtIn && $manifest->id === VixeoCatalogAddon::ID ? route('v1.addons.vixeo.manifest') : null,
        ];
    }
}
