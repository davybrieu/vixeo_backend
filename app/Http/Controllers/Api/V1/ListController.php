<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesSocialProfiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Social\CommentRequest;
use App\Http\Requests\Api\V1\Social\SaveListRequest;
use App\Models\Profile;
use App\Models\ProfileList;
use App\Services\Social\Comments;
use App\Services\Social\Lists;
use App\Services\Social\SocialCards;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Lists and top 10s (docs/social.md): created and changed by the active profile, seen by those who
 * see its content, liked and commented.
 *
 * @tags Listes
 */
class ListController extends Controller
{
    use ResolvesSocialProfiles;

    public function __construct(private Lists $lists, private Comments $comments, private SocialCards $cards) {}

    /**
     * My lists.
     *
     * The most recently changed first. With `item` (`movie:550`), `contains` says which ones have
     * it (« Ajouter à une liste »).
     */
    public function mine(Request $request): JsonResponse
    {
        $profile = $this->author($request);
        $item = (string) $request->query('item', '');
        $containing = $item === '' ? [] : $this->lists->containing($profile, $item);

        return response()->json(['data' => array_map(fn (array $card) => [
            ...$card,
            /** Has the `item` asked. */
            'contains' => in_array($card['id'], $containing, true),
        ], $this->cards->lists($this->lists->of($profile), $profile))]);
    }

    /**
     * Create a list or a top 10.
     */
    public function store(SaveListRequest $request): JsonResponse
    {
        $profile = $this->author($request);
        abort_if($profile->lists()->count() >= 500, 422, __('You have too many lists.'));
        $list = $this->lists->create(
            $profile,
            (string) $request->validated('title'),
            $request->validated('description'),
            (string) $request->validated('kind'),
            $request->items() ?? [],
        );

        return response()->json(['data' => $this->detail($request, $list)], 201);
    }

    /**
     * A list, with its titles in order.
     *
     * 403: a private account the active profile doesn't follow; 404: deleted, hidden or blocked.
     */
    public function show(Request $request, int $list): JsonResponse
    {
        $row = ProfileList::query()->with('profile')->findOrFail($list);
        $this->assertVisible($request, $row->profile, $row->hidden_at !== null);

        return response()->json(['data' => $this->detail($request, $row)]);
    }

    /**
     * Change a list.
     *
     * Its title, description, kind and, when given, its titles in their new order.
     */
    public function update(SaveListRequest $request, int $list): JsonResponse
    {
        $row = $this->owned($request, $list);
        $this->lists->update(
            $row,
            (string) $request->validated('title'),
            $request->validated('description'),
            (string) ($request->validated('kind') ?? $row->kind),
            $request->items(),
        );

        return response()->json(['data' => $this->detail($request, $row->fresh())]);
    }

    /**
     * Delete a list.
     */
    public function destroy(Request $request, int $list): Response
    {
        $this->lists->delete($this->owned($request, $list));

        return response()->noContent();
    }

    /**
     * Add a title at the end of a list.
     *
     * `item`: `movie:550` or `tv:1399`. 422 when the list is full.
     */
    public function addItem(Request $request, int $list): JsonResponse
    {
        $validated = $request->validate(['item' => ['required', 'string', 'regex:/^(movie|tv):\d{1,9}$/']]);
        $row = $this->lists->add($this->owned($request, $list), (string) $validated['item']);

        return response()->json(['data' => $this->detail($request, $row->fresh())]);
    }

    /**
     * Remove a title from a list.
     */
    public function removeItem(Request $request, int $list, string $type, int $id): JsonResponse
    {
        $row = $this->lists->remove($this->owned($request, $list), "{$type}:{$id}");

        return response()->json(['data' => $this->detail($request, $row->fresh())]);
    }

    /**
     * Like a list.
     *
     * Its author is notified; not one's own list (422).
     */
    public function like(Request $request, int $list): JsonResponse
    {
        [$profile, $row] = $this->likable($request, $list);
        $this->lists->like($profile, $row);

        return response()->json(['liked' => true, 'likes_count' => $row->likes()->count()]);
    }

    /**
     * Take back a like.
     */
    public function unlike(Request $request, int $list): JsonResponse
    {
        [$profile, $row] = $this->likable($request, $list);
        $this->lists->unlike($profile, $row);

        return response()->json(['liked' => false, 'likes_count' => $row->likes()->count()]);
    }

    /**
     * Comments of a list.
     *
     * The latest 50, oldest first; `before` (a comment id) for older ones.
     */
    public function comments(Request $request, int $list): JsonResponse
    {
        $row = ProfileList::query()->with('profile')->findOrFail($list);
        $viewer = $this->assertVisible($request, $row->profile, $row->hidden_at !== null);
        $before = $request->integer('before') ?: null;

        return response()->json($this->comments->page($row, $viewer, $before));
    }

    /**
     * Comment a list.
     *
     * Its author is notified.
     */
    public function comment(CommentRequest $request, int $list): JsonResponse
    {
        $profile = $this->author($request);
        $row = ProfileList::query()->with('profile')->findOrFail($list);
        $this->assertVisible($request, $row->profile, $row->hidden_at !== null);
        $comment = $this->comments->add($profile, $row, $request->body(), $request->boolean('contains_spoilers'));

        return response()->json(['data' => SocialCards::comment($comment, $profile, (int) $row->profile_id === (int) $profile->id)], 201);
    }

    /** A list of the active profile (404 otherwise). */
    private function owned(Request $request, int $list): ProfileList
    {
        $profile = $this->author($request);

        return ProfileList::query()->where('profile_id', $profile->id)->with('profile')->findOrFail($list);
    }

    /** @return array{Profile, ProfileList} */
    private function likable(Request $request, int $list): array
    {
        $profile = $this->author($request);
        $row = ProfileList::query()->with('profile')->findOrFail($list);
        $this->assertVisible($request, $row->profile, $row->hidden_at !== null);
        abort_if((int) $row->profile_id === (int) $profile->id, 422, __('You cannot like your own list.'));

        return [$profile, $row];
    }

    /** @return array<string, mixed> */
    private function detail(Request $request, ProfileList $list): array
    {
        $list->load(['profile', 'items']);

        return [
            ...$this->cards->lists(collect([$list]), $this->viewer($request))[0],
            /**
             * The titles, in order (`position` from 0).
             *
             * @var list<array{position: int, type: string, id: int, item_id: string, title: string, year: int|null, poster_url: string|null, backdrop_url: string|null}>
             */
            'items' => $list->items->map(fn ($item) => [
                'position' => (int) $item->position,
                'type' => (string) $item->media_type,
                'id' => (int) $item->tmdb_id,
                'item_id' => $item->itemId(),
                'title' => (string) $item->title,
                'year' => $item->year,
                'poster_url' => F::image($item->poster_path, 'w342'),
                'backdrop_url' => F::image($item->backdrop_path, 'w780'),
            ])->values()->all(),
        ];
    }
}
