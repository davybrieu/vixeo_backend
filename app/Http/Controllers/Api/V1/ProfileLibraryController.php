<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ItemType;
use App\Enums\LibrarySort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LibraryRequest;
use App\Http\Resources\V1\ProfileItemResource;
use App\Models\Profile;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Ready-made lists for clients that don't keep a local copy (e.g. the web app).
 *
 * @tags Synchronisation
 */
class ProfileLibraryController extends Controller
{
    /**
     * Continue watching.
     *
     * Started and not finished, most recent first.
     */
    public function continueWatching(LibraryRequest $request, Profile $profile): AnonymousResourceCollection
    {
        Gate::authorize('view', $profile);

        $items = $profile->items()
            ->continueWatching()
            ->when($request->enum('type', ItemType::class), fn ($q, ItemType $type) => $q->where('type', $type))
            ->limit($request->perPage())
            ->get();

        return ProfileItemResource::collection($items);
    }

    /**
     * Favorites.
     */
    public function favorites(LibraryRequest $request, Profile $profile): AnonymousResourceCollection
    {
        Gate::authorize('view', $profile);

        $query = $profile->items()
            ->where('is_favorite', true)
            ->when($request->enum('type', ItemType::class), fn ($q, ItemType $type) => $q->where('type', $type))
            ->when($request->has('watched'), fn ($q) => $q->where('is_watched', $request->boolean('watched')));

        match ($request->enum('sort', LibrarySort::class) ?? LibrarySort::Recent) {
            LibrarySort::Recent => $query->orderByDesc('favorited_at'),
            LibrarySort::Title => $query->orderByRaw('lower(title)'),
            // Unknown years last, on PostgreSQL as on SQLite.
            LibrarySort::Year => $query->orderByRaw('year is null')->orderByDesc('year')->orderByRaw('lower(title)'),
        };

        return ProfileItemResource::collection($query->orderBy('id')->paginate($request->perPage()));
    }

    /**
     * Watch history.
     *
     * Watched items, most recent first.
     */
    public function history(LibraryRequest $request, Profile $profile): AnonymousResourceCollection
    {
        Gate::authorize('view', $profile);

        $items = $profile->items()
            ->where('is_watched', true)
            ->when($request->enum('type', ItemType::class), fn ($q, ItemType $type) => $q->where('type', $type))
            ->orderByDesc('watched_at')
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return ProfileItemResource::collection($items);
    }
}
