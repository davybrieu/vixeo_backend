<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\SocialNotification;
use App\Services\Social\Audience;
use App\Services\Social\SocialNotifications;
use App\Services\Tmdb\AgeGate;
use App\Services\Tmdb\TitleSummaries;
use App\Services\Tmdb\TmdbFormat as F;
use App\Services\Tmdb\UpcomingEpisodes;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The app's « Notifications » page (docs/social.md): what other profiles did for the active profile
 * (followed it, asked to follow it, accepted its request, liked a review) and the next episodes of
 * the TV shows of its « À voir ».
 */
class NotificationController extends Controller
{
    /** Episodes out for less than that many days: « Nouveaux épisodes ». */
    private const RECENT_DAYS = 7;

    /**
     * Notifications of the active profile.
     *
     * The most recent first, 30 per page. `type`: `follow`, `follow_request` (to accept or decline,
     * `GET /social/requests`), `follow_accepted`, `review_like` (with `review`), `review_comment`
     * (with `review` and `comment`), `list_like` (with `list`), `list_comment` (with `list` and
     * `comment`), `moderation` (a decision of the moderation, `text`). `unread_count`: the badge
     * of the navigation.
     */
    public function index(Request $request, Audience $audience): JsonResponse
    {
        $profile = $this->profile($request);
        $page = SocialNotification::query()->where('profile_id', $profile->id)
            ->whereNotIn('actor_profile_id', $audience->blocked($profile))
            ->with(['actor', 'rating', 'list', 'comment'])->latest('created_at')->latest('id')->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (SocialNotification $n) => [
                'id' => (int) $n->id,
                'type' => (string) $n->type,
                'created_at' => (string) $n->created_at?->toIso8601String(),
                'read' => $n->read_at !== null,
                /** Who did it. */
                'actor' => [
                    'id' => (int) $n->actor->id,
                    'username' => (string) $n->actor->username,
                    'name' => (string) $n->actor->name,
                    'avatar' => $n->actor->avatar,
                    'photo_url' => $n->actor->photoUrl(),
                ],
                /** The review liked (`review_like`). */
                'review' => $n->rating === null ? null : [
                    'review_id' => (int) $n->rating->id,
                    'type' => $n->rating->type,
                    'id' => (int) $n->rating->tmdb_id,
                    'title' => (string) $n->rating->title,
                    'season_number' => $n->rating->season_number === null ? null : (int) $n->rating->season_number,
                    'episode_number' => $n->rating->episode_number === null ? null : (int) $n->rating->episode_number,
                    'value' => (float) $n->rating->value,
                    'poster_url' => F::image($n->rating->poster_path === null ? null : (string) $n->rating->poster_path, 'w342'),
                ],
                /** The list liked or commented (`list_like`, `list_comment`). */
                'list' => $n->list === null ? null : [
                    'id' => (int) $n->list->id,
                    'title' => (string) $n->list->title,
                ],
                /** The comment written (`review_comment`, `list_comment`). */
                'comment' => $n->comment === null ? null : [
                    'id' => (int) $n->comment->id,
                    'body' => SocialNotifications::excerpt($n->comment->body, 140),
                    'contains_spoilers' => (bool) $n->comment->contains_spoilers,
                ],
                /** A decision of the moderation (`moderation`). */
                'text' => $n->type === SocialNotification::MODERATION ? (string) ($n->data['text'] ?? '') : null,
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'unread_count' => $this->unread($profile),
        ]);
    }

    /**
     * Unread notifications (the badge of the navigation).
     */
    public function count(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $this->unread($this->profile($request))]);
    }

    /**
     * Mark every notification as read (the page was opened).
     */
    public function read(Request $request): Response
    {
        SocialNotification::query()->where('profile_id', $this->profile($request)->id)
            ->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }

    /**
     * Next episodes of the « À voir » TV shows.
     *
     * `upcoming`: the next episode of each show TMDb knows, the soonest first; `recent`: episodes
     * out for less than 7 days, the most recent first. `available_at` is an estimate made not to
     * be early (08:00 UTC on streaming platforms, 21:00 in the show's country otherwise).
     */
    public function episodes(Request $request, TitleSummaries $summaries, AgeGate $ageGate): JsonResponse
    {
        $profile = $this->profile($request);
        $ids = ProfileItem::query()->where('profile_id', $profile->id)->where('is_favorite', true)
            ->where('item_id', 'like', 'tv:%')->pluck('item_id')
            ->map(fn (string $id) => preg_match('/^tv:(\d+)$/', $id, $m) === 1 ? (int) $m[1] : 0)
            ->filter()->unique()->take(200)->values()->all();

        $now = CarbonImmutable::now();
        $upcoming = [];
        $recent = [];
        foreach ($summaries->shows($ids) as $id => $show) {
            if ($show === null || ! $ageGate->allows($profile, $show)) {
                continue;
            }
            foreach (['next_episode_to_air' => true, 'last_episode_to_air' => false] as $key => $next) {
                $episode = $show[$key] ?? null;
                $at = is_array($episode) ? UpcomingEpisodes::availableAt($show, F::string($episode, 'air_date')) : null;
                if ($at === null) {
                    continue;
                }
                $row = self::episode($id, $show, $episode, $at);
                if ($at->isFuture()) {
                    $upcoming[] = $row;
                } elseif ($at->gte($now->subDays(self::RECENT_DAYS))) {
                    $recent[] = $row;
                }
            }
        }
        usort($upcoming, fn (array $a, array $b) => $a['available_at'] <=> $b['available_at']);
        usort($recent, fn (array $a, array $b) => $b['available_at'] <=> $a['available_at']);

        return response()->json(['upcoming' => $upcoming, 'recent' => $recent]);
    }

    /**
     * @param  array<string, mixed>  $show
     * @param  array<string, mixed>  $episode
     * @return array<string, mixed>
     */
    private static function episode(int $id, array $show, array $episode, CarbonImmutable $at): array
    {
        return [
            'show' => [
                'id' => (int) $id,
                'name' => F::string($show, 'name') ?? '',
                'poster_url' => F::image(F::string($show, 'poster_path'), 'w342'),
                'backdrop_url' => F::image(F::string($show, 'backdrop_path'), 'w780'),
            ],
            'season_number' => F::int($episode, 'season_number'),
            'episode_number' => F::int($episode, 'episode_number'),
            'name' => F::string($episode, 'name'),
            'still_url' => F::image(F::string($episode, 'still_path'), 'w300'),
            'air_date' => F::string($episode, 'air_date'),
            /** Estimated time it is out, ISO 8601 (UTC). */
            'available_at' => $at->utc()->toIso8601String(),
        ];
    }

    private function profile(Request $request): Profile
    {
        $profile = ResolveProfile::profile($request);
        abort_unless($profile instanceof Profile, 422, 'Sélectionnez un profil.');
        abort_if($profile->is_kid, 403, 'Les profils enfants ne peuvent pas utiliser les fonctions sociales.');

        return $profile;
    }

    private function unread(Profile $profile): int
    {
        return SocialNotification::query()->where('profile_id', $profile->id)->whereNull('read_at')->count();
    }
}
