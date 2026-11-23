<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesSocialProfiles;
use App\Http\Controllers\Controller;
use App\Models\DiaryEntry;
use App\Models\Profile;
use App\Services\Social\Diary;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The diary (« Journal ») and the « Bilan de l'année » (docs/social.md).
 *
 * @tags Social
 */
class DiaryController extends Controller
{
    use ResolvesSocialProfiles;

    public function __construct(private Diary $diary) {}

    /**
     * A member's diary.
     *
     * What it watched, the most recent first (30 per page), with its rating. Written by itself
     * when a title becomes watched.
     */
    public function index(Request $request, string $username): JsonResponse
    {
        $member = $this->visibleMember($request, $username);
        $page = $this->diary->page($member);

        return response()->json([
            'data' => $this->diary->cards($member, $page->getCollection()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /**
     * A member's « Bilan de l'année ».
     *
     * `year` (this year by default): titles, episodes, minutes, months, genres, best ratings.
     */
    public function stats(Request $request, string $username): JsonResponse
    {
        $member = $this->visibleMember($request, $username);
        $year = $request->integer('year') ?: (int) now()->year;
        abort_if($year < 1990 || $year > (int) now()->year, 422, __('Choose a year.'));

        return response()->json(['data' => $this->diary->stats($member, $year)]);
    }

    /**
     * Log a viewing.
     *
     * Another viewing (« Revu ») or a past one: `item` (`movie:550`, `tv:1399`), maybe
     * `season_number` and `episode_number`, `watched_at` (now by default). `rewatch` when the diary
     * already has it.
     */
    public function store(Request $request, Profile $profile): JsonResponse
    {
        Gate::authorize('update', $profile);
        $validated = $request->validate([
            'item' => ['required', 'string', 'regex:/^(movie|tv):\d{1,9}$/'],
            'season_number' => ['nullable', 'integer', 'min:0', 'max:500', 'required_with:episode_number'],
            'episode_number' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'episode_name' => ['nullable', 'string', 'max:255'],
            'watched_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);
        $at = isset($validated['watched_at']) ? CarbonImmutable::parse($validated['watched_at']) : null;
        $entry = $this->diary->log(
            $profile,
            (string) $validated['item'],
            isset($validated['episode_number']) ? (int) $validated['season_number'] : null,
            isset($validated['episode_number']) ? (int) $validated['episode_number'] : null,
            $at,
            $validated['episode_name'] ?? null,
        );

        return response()->json(['data' => $this->diary->cards($profile, collect([$entry]))[0]], 201);
    }

    /**
     * Remove an entry of the diary.
     */
    public function destroy(Profile $profile, int $entry): Response
    {
        Gate::authorize('update', $profile);
        $this->diary->remove(DiaryEntry::query()->where('profile_id', $profile->id)->findOrFail($entry));

        return response()->noContent();
    }
}
