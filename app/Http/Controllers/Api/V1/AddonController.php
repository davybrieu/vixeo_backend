<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InstallAddonRequest;
use App\Http\Requests\Api\V1\OrderAddonsRequest;
use App\Http\Requests\Api\V1\UpdateAddonRequest;
use App\Http\Resources\V1\Addons\AddonResource;
use App\Services\Addons\Addons;
use App\Services\Addons\InvalidAddon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * The plug-ins of the account (docs/plugins.md): add-ons in the Stremio format, for catalogs
 * (rows of the home) and streams (providers of the sources panel). The Vixeo catalog comes first,
 * built in.
 */
class AddonController extends Controller
{
    public function __construct(private Addons $addons) {}

    /**
     * The account's plug-ins, the Vixeo catalog first, then those of every account, then its own.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->addons->refreshStale($request->user());

        return AddonResource::collection($this->addons->installed($request->user()));
    }

    /**
     * Installs a plug-in from its address. 422 with a French message (`errors.url`) when the
     * address is not a plug-in, does not answer, is private, or the plug-in is already installed.
     */
    public function store(InstallAddonRequest $request): JsonResponse
    {
        try {
            $addon = $this->addons->install($request->user(), $request->addonUrl());
        } catch (InvalidAddon $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        return (new AddonResource(['key' => (string) $addon->id, 'manifest' => $addon->parsedManifest(), 'addon' => $addon]))
            ->response()
            ->setStatusCode(201);
    }

    /** Turns one of the account's plug-ins on or off. */
    public function update(UpdateAddonRequest $request, string $addon): AddonResource
    {
        $model = $this->addons->owned($request->user(), $addon);
        $model->update(['enabled' => $request->boolean('enabled')]);

        return new AddonResource(['key' => (string) $model->id, 'manifest' => $model->parsedManifest(), 'addon' => $model]);
    }

    /** New order of the account's own plug-ins. */
    public function order(OrderAddonsRequest $request): Response
    {
        $this->addons->reorder($request->user(), $request->ids());

        return response()->noContent();
    }

    /** Removes one of the account's plug-ins. */
    public function destroy(Request $request, string $addon): Response
    {
        $this->addons->owned($request->user(), $addon)->delete();

        return response()->noContent();
    }
}
