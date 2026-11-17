<?php

namespace App\Http\Resources\V1\Addons;

use App\Models\Addon;
use App\Services\Addons\AddonManifest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plug-in of the account (docs/plugins.md): a source of streams for the player. The address
 * of a plug-in is never sent back whole (it may hold a personal key): only its host.
 *
 * @property array{key: string, manifest: AddonManifest, addon: Addon} $resource
 */
class AddonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AddonManifest $manifest */
        $manifest = $this->resource['manifest'];
        /** @var Addon $addon */
        $addon = $this->resource['addon'];

        return [
            /** The plug-in's number. */
            'id' => (string) $this->resource['key'],
            'name' => (string) $manifest->name,
            'description' => $manifest->description === null ? null : (string) $manifest->description,
            'version' => (string) $manifest->version,
            /** Logo (https only), null when the plug-in has none. */
            'logo_url' => $manifest->logo === null ? null : (string) $manifest->logo,
            /** Host of the plug-in's address ("addon.example.com"). */
            'host' => (string) $addon->host(),
            /**
             * What it serves, in the Stremio words ("catalog", "meta", "stream", "subtitles"). Vixeo
             * only uses "stream": the sources of the player.
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
            /** Whether it gives sources of movies or TV shows (a plug-in installed before may not). */
            'streams' => (bool) $manifest->servesStreams(),
            /**
             * Always 0: the catalogs of plug-ins are no longer shown (the catalog is TMDb's).
             *
             * @deprecated
             */
            'catalogs' => 0,
            'enabled' => (bool) $addon->enabled,
            /**
             * Always false: there is no built-in plug-in any more.
             *
             * @deprecated
             */
            'built_in' => false,
            /** Installed on the server for every account: the account cannot change it. */
            'shared' => $addon->user_id === null,
            /** For adults only (hidden from profiles with an age limit). */
            'adult' => (bool) $manifest->adult,
            /**
             * Always null (it was the Stremio address of the Vixeo catalog, removed).
             *
             * @deprecated
             */
            'manifest_url' => null,
        ];
    }
}
