<?php

namespace App\Services\Social;

use App\Enums\RatedType;
use App\Models\Profile;
use App\Models\TmdbRating;
use Illuminate\Support\Facades\DB;

/**
 * Tastes in common (docs/social.md): how close two profiles rate the movies and TV shows both
 * rated (« 82 % de goûts en commun »), and the accounts to follow.
 */
class Taste
{
    /** Titles both must have rated before a score means something. */
    public const MIN_COMMON = 5;

    public function __construct(private Audience $audience) {}

    /**
     * Compatibility of [a] and [b]: 100 when they give the same ratings, 0 when 5 points apart on
     * average; null below MIN_COMMON titles rated by both.
     *
     * @return array{score: int|null, common: int}
     */
    public function compatibility(Profile $a, Profile $b): array
    {
        $mine = $this->ratings([$a->id]);

        return self::score($mine[$a->id] ?? [], $this->ratings([$b->id])[$b->id] ?? []);
    }

    /**
     * Compatibility of [viewer] with each of [profiles] (the « Pour toi » feed): profile id →
     * 0 to 100, or null below MIN_COMMON titles rated by both. Two queries whatever the number.
     *
     * @param  list<int>  $profiles
     * @return array<int, int|null>
     */
    public function compatibilities(Profile $viewer, array $profiles): array
    {
        $mine = $this->ratings([(int) $viewer->id])[(int) $viewer->id] ?? [];
        $theirs = $mine === [] || $profiles === [] ? [] : $this->ratings($profiles);

        $scores = [];
        foreach ($profiles as $id) {
            $scores[$id] = $mine === [] ? null : self::score($mine, $theirs[$id] ?? [])['score'];
        }

        return $scores;
    }

    /**
     * Accounts [viewer] could follow: followed by those it follows, then close tastes, then the
     * most followed; not those it follows or asked to follow, nor blocked ones.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestions(Profile $viewer, int $limit = 20): array
    {
        $known = DB::table('profile_follows')->where('follower_profile_id', $viewer->id)
            ->pluck('followed_profile_id')->map(intval(...))->push((int) $viewer->id)
            ->merge($this->audience->blocked($viewer))->unique()->values()->all();
        $following = DB::table('profile_follows')->where('follower_profile_id', $viewer->id)
            ->whereNotNull('accepted_at')->pluck('followed_profile_id')->all();

        // Followed by the accounts it follows, and by how many of them.
        $friendsOf = DB::table('profile_follows')->whereIn('follower_profile_id', $following)->whereNotNull('accepted_at')
            ->whereNotIn('followed_profile_id', $known)
            ->groupBy('followed_profile_id')->selectRaw('followed_profile_id as id, count(*) as total')
            ->orderByDesc('total')->limit(50)->pluck('total', 'id')->map(intval(...));
        $popular = DB::table('profile_follows')->whereNotNull('accepted_at')->whereNotIn('followed_profile_id', $known)
            ->groupBy('followed_profile_id')->selectRaw('followed_profile_id as id, count(*) as total')
            ->orderByDesc('total')->limit(50)->pluck('total', 'id')->map(intval(...));
        // Profiles who rated the same titles (the most active first).
        $mine = $this->ratings([$viewer->id])[$viewer->id] ?? [];
        $raters = $mine === [] ? collect() : TmdbRating::query()->whereIn('target', array_keys($mine))
            ->whereNotIn('profile_id', $known)->groupBy('profile_id')->selectRaw('profile_id as id, count(*) as total')
            ->orderByDesc('total')->limit(50)->pluck('total', 'id')->map(intval(...));

        $ids = $friendsOf->keys()->merge($raters->keys())->merge($popular->keys())->unique()->values()->all();
        $profiles = Profile::query()->whereIn('id', $ids)->where('is_kid', false)->whereNotNull('username')
            ->whereNull('suspended_at')->withCount('followers')->get();
        $theirs = $this->ratings($profiles->modelKeys());

        return $profiles->map(function (Profile $profile) use ($friendsOf, $mine, $theirs) {
            $compatibility = self::score($mine, $theirs[$profile->id] ?? []);
            $friends = $friendsOf[$profile->id] ?? 0;

            return [
                'profile' => $profile,
                'friends' => $friends,
                'compatibility' => $compatibility['score'],
                'rank' => $friends * 1000 + ($compatibility['score'] ?? 0) * 5 + min((int) $profile->followers_count, 400),
            ];
        })
            ->sortByDesc('rank')->take($limit)->values()
            ->map(fn (array $row) => [
                ...SocialCards::identity($row['profile']),
                'followers_count' => (int) $row['profile']->followers_count,
                /** Tastes in common, 0 to 100 (null: not enough titles rated by both). */
                'compatibility' => $row['compatibility'],
                /** Why it is suggested: « Suivi par 3 de vos abonnements », « 82 % de goûts en commun », « Populaire ». */
                'reason' => match (true) {
                    $row['friends'] > 0 => $row['friends'] === 1 ? 'Suivi par un de vos abonnements' : "Suivi par {$row['friends']} de vos abonnements",
                    $row['compatibility'] !== null => "{$row['compatibility']} % de goûts en commun",
                    default => 'Populaire sur Vixeo',
                },
            ])->all();
    }

    /**
     * Ratings of movies and TV shows (not episodes) of [profiles], by profile then target.
     *
     * @param  list<int>  $profiles
     * @return array<int, array<string, float>>
     */
    private function ratings(array $profiles): array
    {
        $ratings = [];
        TmdbRating::query()->whereIn('profile_id', $profiles)->where('type', '!=', RatedType::Episode->value)
            ->get(['profile_id', 'target', 'value'])
            ->each(function (TmdbRating $r) use (&$ratings) {
                $ratings[(int) $r->profile_id][(string) $r->target] = (float) $r->value;
            });

        return $ratings;
    }

    /**
     * @param  array<string, float>  $a
     * @param  array<string, float>  $b
     * @return array{score: int|null, common: int}
     */
    private static function score(array $a, array $b): array
    {
        $common = array_intersect_key($a, $b);
        if (count($common) < self::MIN_COMMON) {
            return ['score' => null, 'common' => count($common)];
        }
        $gap = 0.0;
        foreach ($common as $target => $value) {
            $gap += abs($value - $b[$target]);
        }

        return ['score' => (int) max(0, min(100, round(100 * (1 - $gap / count($common) / 5)))), 'common' => count($common)];
    }
}
