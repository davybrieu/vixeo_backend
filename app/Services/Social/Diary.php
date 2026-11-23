<?php

namespace App\Services\Social;

use App\Enums\RatedType;
use App\Models\DiaryEntry;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\TmdbRating;
use App\Services\Tmdb\TmdbFormat as F;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The viewings of a profile (docs/social.md): each movie, TV show or episode watched, on its day.
 * Written by itself when a title becomes watched (the synchronisation: the player at the end, or
 * the « Vu » button) and when the app logs an episode or another viewing (`rewatch`). Each entry
 * goes to the feed and to the « Activité » of the profile; the « Bilan de l'année » counts them.
 * The app no longer shows them as a « Journal » (developer's request, 23/11/2026).
 */
class Diary
{
    public function __construct(private TitleSnapshots $snapshots, private Activities $activities) {}

    /**
     * Movies and TV shows that just became watched (App\Services\ProfileSync).
     *
     * @param  list<ProfileItem>  $items
     */
    public function watched(Profile $profile, array $items): void
    {
        $items = array_values(array_filter($items, fn (ProfileItem $item) => $item->source === 'tmdb'
            && TitleSnapshots::parse((string) $item->item_id) !== null && $item->watched_at !== null));
        if ($items === []) {
            return;
        }
        $snapshots = $this->snapshots->of(array_map(fn (ProfileItem $item) => (string) $item->item_id, $items));
        foreach ($items as $item) {
            $snapshot = $snapshots[$item->item_id] ?? null;
            [$type, $id] = TitleSnapshots::parse((string) $item->item_id);
            $this->write($profile, [
                'media_type' => $type->value,
                'tmdb_id' => $id,
                'title' => $snapshot['title'] ?? (string) $item->title,
                'year' => $snapshot['year'] ?? $item->year,
                'poster_url' => $snapshot === null ? $item->poster_url : F::image($snapshot['poster_path'], 'w342'),
                'runtime' => $snapshot['runtime'] ?? null,
                'genres' => $snapshot['genres'] ?? null,
            ], CarbonImmutable::instance($item->watched_at), null);
        }
    }

    /**
     * Another viewing logged by the app (« Revu »), or a past one; `rewatch` by itself when the
     * diary already has the title (or the episode).
     */
    public function log(Profile $profile, string $itemId, ?int $season, ?int $episode, ?CarbonImmutable $at, ?string $episodeName = null): DiaryEntry
    {
        [$type, $id] = TitleSnapshots::parse($itemId) ?? abort(422, __('Unknown title.'));
        $snapshot = $this->snapshots->of([$itemId])[$itemId] ?? abort(503, __('The catalog is not available right now.'));

        return $this->write($profile, [
            'media_type' => $type->value,
            'tmdb_id' => $id,
            'season_number' => $season,
            'episode_number' => $episode,
            'episode_name' => $episodeName,
            'title' => $snapshot['title'],
            'year' => $snapshot['year'],
            'poster_url' => F::image($snapshot['poster_path'], 'w342'),
            'runtime' => $snapshot['runtime'],
            'genres' => $snapshot['genres'],
        ], $at ?? CarbonImmutable::now(), null);
    }

    /**
     * An entry as the app shows it, with the profile's rating of the title (or episode).
     *
     * @param  Collection<int, DiaryEntry>  $entries
     * @return list<array<string, mixed>>
     */
    public function cards(Profile $profile, Collection $entries): array
    {
        $ratings = TmdbRating::query()->where('profile_id', $profile->id)
            ->whereIn('tmdb_id', $entries->pluck('tmdb_id')->unique()->all())->get()
            ->keyBy(fn (TmdbRating $r) => self::key($r->type->value === 'movie' ? 'movie' : 'tv', $r->tmdb_id, $r->season_number, $r->episode_number));

        return $entries->map(fn (DiaryEntry $entry) => [
            'id' => (int) $entry->id,
            'type' => (string) $entry->media_type,
            'tmdb_id' => (int) $entry->tmdb_id,
            'item_id' => "{$entry->media_type}:{$entry->tmdb_id}",
            'title' => (string) $entry->title,
            'year' => $entry->year === null ? null : (int) $entry->year,
            'season_number' => $entry->season_number === null ? null : (int) $entry->season_number,
            'episode_number' => $entry->episode_number === null ? null : (int) $entry->episode_number,
            'episode_name' => $entry->episode_name === null ? null : (string) $entry->episode_name,
            'poster_url' => $entry->poster_url === null ? null : (string) $entry->poster_url,
            /** When it was watched, ISO 8601. */
            'watched_at' => (string) $entry->watched_at->toIso8601String(),
            /** Watched again. */
            'rewatch' => (bool) $entry->rewatch,
            /** The profile's rating of the title (or episode), 0.5 to 10. */
            'rating' => ($r = $ratings->get(self::key($entry->media_type, $entry->tmdb_id, $entry->season_number, $entry->episode_number))) === null ? null : (float) $r->value,
        ])->values()->all();
    }

    /**
     * The « Bilan de l'année » of [profile]: what it watched in [year] (UTC), its genres, months
     * and best ratings.
     *
     * @return array<string, mixed>
     */
    public function stats(Profile $profile, int $year): array
    {
        $from = CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'UTC');
        $entries = DiaryEntry::query()->where('profile_id', $profile->id)
            ->where('watched_at', '>=', $from)->where('watched_at', '<', $from->addYear())->get();
        $this->complete($entries);

        $genres = [];
        foreach ($entries as $entry) {
            foreach ($entry->genres ?? [] as $genre) {
                $genres[$genre] = ($genres[$genre] ?? 0) + 1;
            }
        }
        arsort($genres);
        $months = array_fill(0, 12, 0);
        foreach ($entries as $entry) {
            $months[(int) $entry->watched_at->format('n') - 1]++;
        }
        $shows = $entries->where('media_type', 'tv')->groupBy('tmdb_id')->sortByDesc(fn (Collection $g) => $g->count());
        $topShow = $shows->first();

        $reviews = TmdbRating::query()->where('profile_id', $profile->id)
            ->where('rated_at', '>=', $from)->where('rated_at', '<', $from->addYear())->get();
        $best = $reviews->where('type', '!=', RatedType::Episode)
            ->sortBy([['value', 'desc'], ['rated_at', 'desc']])->take(5)->values();

        return [
            'year' => $year,
            /** Movies, TV shows and episodes logged. */
            'entries_count' => (int) $entries->count(),
            'movies_count' => (int) $entries->where('media_type', 'movie')->count(),
            'episodes_count' => (int) $entries->whereNotNull('episode_number')->count(),
            'shows_count' => (int) $shows->count(),
            'rewatches_count' => (int) $entries->where('rewatch', true)->count(),
            /** Minutes watched (movies and episodes whose length TMDb knows). */
            'minutes' => (int) $entries->sum(fn (DiaryEntry $e) => $e->media_type === 'tv' && $e->episode_number === null ? 0 : (int) $e->runtime),
            /**
             * Entries per month, January first.
             *
             * @var list<int>
             */
            'months' => $months,
            /** The 5 genres seen most. */
            'genres' => collect(array_slice($genres, 0, 5, true))->map(fn (int $count, string $name) => ['name' => $name, 'count' => $count])->values()->all(),
            'ratings_count' => (int) $reviews->count(),
            'average_rating' => $reviews->isEmpty() ? null : round((float) $reviews->avg('value'), 1),
            /**
             * The best rated movies and TV shows of the year.
             *
             * @var list<array{type: string, id: int, title: string, poster_url: string|null, value: float}>
             */
            'best_rated' => $best->map(fn (TmdbRating $r) => [
                'type' => $r->type->value === 'movie' ? 'movie' : 'tv',
                'id' => (int) $r->tmdb_id,
                'title' => (string) $r->title,
                'poster_url' => F::image($r->poster_path, 'w342'),
                'value' => (float) $r->value,
            ])->all(),
            /** The TV show with the most entries. */
            'top_show' => $topShow === null ? null : [
                'id' => (int) $topShow->first()->tmdb_id,
                'title' => (string) $topShow->first()->title,
                'poster_url' => $topShow->first()->poster_url === null ? null : (string) $topShow->first()->poster_url,
                'entries_count' => (int) $topShow->count(),
            ],
        ];
    }

    /** @param  array<string, mixed>  $fields */
    private function write(Profile $profile, array $fields, CarbonImmutable $at, ?bool $rewatch): DiaryEntry
    {
        $same = DiaryEntry::query()->where('profile_id', $profile->id)
            ->where('media_type', $fields['media_type'])->where('tmdb_id', $fields['tmdb_id'])
            ->where('season_number', $fields['season_number'] ?? null)->where('episode_number', $fields['episode_number'] ?? null);
        // Written twice (a sync sent again): the same entry.
        $existing = (clone $same)->where('watched_at', $at)->first();
        if ($existing !== null) {
            return $existing;
        }
        $entry = DiaryEntry::query()->create([
            ...$fields,
            'profile_id' => $profile->id,
            'title' => mb_substr((string) $fields['title'], 0, 255),
            'watched_at' => $at,
            'rewatch' => $rewatch ?? (clone $same)->where('watched_at', '<', $at)->exists(),
            'created_at' => now(),
        ]);
        if (! $profile->is_kid) {
            $this->activities->watched($entry);
        }

        return $entry;
    }

    /**
     * Length and genres of the entries written while TMDb was down.
     *
     * @param  Collection<int, DiaryEntry>  $entries
     */
    private function complete(Collection $entries): void
    {
        $missing = $entries->filter(fn (DiaryEntry $e) => $e->genres === null)->take(200);
        if ($missing->isEmpty()) {
            return;
        }
        $snapshots = $this->snapshots->of($missing->map(fn (DiaryEntry $e) => "{$e->media_type}:{$e->tmdb_id}")->unique()->values()->all());
        foreach ($missing as $entry) {
            $snapshot = $snapshots["{$entry->media_type}:{$entry->tmdb_id}"] ?? null;
            if ($snapshot !== null) {
                $entry->forceFill(['runtime' => $snapshot['runtime'], 'genres' => $snapshot['genres']])->save();
            }
        }
    }

    private static function key(string $type, int $id, ?int $season, ?int $episode): string
    {
        return "{$type}:{$id}:{$season}:{$episode}";
    }
}
