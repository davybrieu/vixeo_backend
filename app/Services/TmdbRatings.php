<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Enums\RatedType;
use App\Models\Profile;
use App\Models\TmdbLink;
use App\Models\TmdbRating;
use App\Services\Tmdb\RatingTarget;
use App\Services\Tmdb\TmdbAccount;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbFormat as F;
use App\Services\Tmdb\TmdbUnavailableException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The profile's copy of its TMDb ratings (docs/compte-tmdb.md), with its comments: TMDb keeps the
 * ratings (the same on every profile linked to the account and on themoviedb.org), Vixeo keeps the
 * comments, the date of each rating and what "Mes avis" shows. Ratings given in Vixeo are copied
 * at once; the others (themoviedb.org…) when the copy is refreshed, at most every 10 minutes.
 */
class TmdbRatings
{
    /** Seconds before the copy is read again from TMDb. */
    public const STALE_AFTER = 600;

    /** Pages of 20 ratings read per type, at most. */
    private const MAX_PAGES = 50;

    public function __construct(private TmdbAccount $account, private TmdbClient $tmdb) {}

    /**
     * Every rating of the linked account, the most recent first.
     *
     * @return Collection<int, TmdbRating>
     */
    public function all(Profile $profile, TmdbLink $link): Collection
    {
        $this->refreshIfStale($profile, $link);

        return $this->query($profile, $link)->latest('rated_at')->latest('id')->get();
    }

    /** The copy of one rating, null when there is none. */
    public function find(Profile $profile, TmdbLink $link, RatingTarget $target): ?TmdbRating
    {
        return $this->query($profile, $link)->where('target', $target->path)->first();
    }

    /**
     * The copies of a season's episode ratings, by episode number.
     *
     * @return Collection<int, TmdbRating>
     */
    public function season(Profile $profile, TmdbLink $link, int $showId, int $season): Collection
    {
        return $this->query($profile, $link)
            ->where('type', RatedType::Episode)
            ->where('tmdb_id', $showId)
            ->where('season_number', $season)
            ->get()
            ->keyBy('episode_number');
    }

    /**
     * A rating given in Vixeo (already saved on TMDb). [$comment] is only changed when given
     * (null removes it).
     */
    public function saved(Profile $profile, TmdbLink $link, RatingTarget $target, float $value, bool $withComment, ?string $comment): TmdbRating
    {
        $row = $this->query($profile, $link)->firstOrNew(['target' => $target->path]);
        if (! $row->exists || $row->value !== $value) {
            $row->rated_at = now();
        }
        $row->fill([
            'account_id' => $link->account_id,
            'type' => $target->type,
            'tmdb_id' => $target->id,
            'season_number' => $target->season,
            'episode_number' => $target->episode,
            'value' => $value,
        ]);
        if ($withComment) {
            $row->comment = $comment;
        }
        if (! $row->exists || $row->title === '') {
            $row->fill($this->describe($target));
        }
        $row->profile()->associate($profile);
        $row->save();

        return $row;
    }

    /** A rating removed in Vixeo (or found removed on TMDb): its comment goes with it. */
    public function removed(Profile $profile, TmdbLink $link, RatingTarget $target): void
    {
        $this->query($profile, $link)->where('target', $target->path)->delete();
    }

    /** The profile no longer has this TMDb account (unlinked, another one linked). */
    public function forget(Profile $profile, ?int $keepAccountId = null): void
    {
        $profile->tmdbRatings()->when($keepAccountId !== null, fn ($q) => $q->where('account_id', '!=', $keepAccountId))->delete();
    }

    /** Reads the account's ratings again, unless done in the last 10 minutes. */
    public function refreshIfStale(Profile $profile, TmdbLink $link): void
    {
        $key = "tmdb-ratings:{$profile->id}:{$link->account_id}";
        if (Cache::has($key)) {
            return;
        }

        try {
            $this->refresh($profile, $link);
            Cache::put($key, true, self::STALE_AFTER);
        } catch (TmdbUnavailableException) {
            // TMDb down: the copy is shown as it is, and read again next time.
            Log::warning('TMDb ratings not refreshed');
        }
    }

    /** Copies every rating of the account: new ones added, changed ones updated, removed ones deleted. */
    public function refresh(Profile $profile, TmdbLink $link): void
    {
        $remote = [];
        foreach (RatedType::cases() as $type) {
            $items = $this->fetchAll($link, $type);
            $shows = $type === RatedType::Episode
                ? $this->tmdb->getMany(array_values(array_unique(array_map(fn (array $e) => 'tv/'.(int) ($e['show_id'] ?? 0), $items))))
                : [];
            foreach ($items as $item) {
                $attributes = self::fromTmdb($type, $item, $shows['tv/'.(int) ($item['show_id'] ?? 0)] ?? null);
                $remote[$attributes['target']] ??= $attributes;
            }
        }

        DB::transaction(function () use ($profile, $link, $remote) {
            $existing = $this->query($profile, $link)->get()->keyBy('target');
            // TMDb gives no date: a rating seen for the first time keeps TMDb's order (newest first).
            $at = now();
            foreach ($remote as $target => $attributes) {
                $row = $existing->pull($target);
                if ($row instanceof TmdbRating) {
                    $row->fill($attributes)->save();
                } else {
                    $row = new TmdbRating($attributes + ['account_id' => $link->account_id, 'rated_at' => $at]);
                    $row->profile()->associate($profile)->save();
                    $at = $at->copy()->subSecond();
                }
            }
            // Removed on TMDb (themoviedb.org…): removed here, with the comment.
            $existing->each->delete();
        });
    }

    /** @return Builder<TmdbRating> */
    private function query(Profile $profile, TmdbLink $link): Builder
    {
        return TmdbRating::query()->where('profile_id', $profile->id)->where('account_id', $link->account_id);
    }

    /** @return list<array<string, mixed>> */
    private function fetchAll(TmdbLink $link, RatedType $type): array
    {
        $items = [];
        $page = 1;
        do {
            $result = $this->account->rated($link, $type, $page);
            array_push($items, ...$result['results']);
        } while ($page++ < min($result['total_pages'], self::MAX_PAGES));

        return $items;
    }

    /**
     * Columns of a TMDb rated item.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>|null  $show  TV show of an episode
     * @return array<string, mixed>
     */
    private static function fromTmdb(RatedType $type, array $item, ?array $show): array
    {
        $episode = $type === RatedType::Episode;
        $title = $episode ? ($show ?? []) : $item;
        $target = $episode
            ? RatingTarget::episode((int) ($item['show_id'] ?? 0), (int) ($item['season_number'] ?? 0), (int) ($item['episode_number'] ?? 0))
            : RatingTarget::title($type === RatedType::Movie ? MediaType::Movie : MediaType::Tv, (int) ($item['id'] ?? 0));

        return [
            'target' => $target->path,
            'type' => $type,
            'tmdb_id' => $target->id,
            'season_number' => $target->season,
            'episode_number' => $target->episode,
            'value' => (float) ($item['rating'] ?? 0),
            'title' => (string) (F::string($title, $type === RatedType::Movie ? 'title' : 'name') ?? ''),
            'episode_name' => $episode ? F::string($item, 'name') : null,
            'date' => F::string($item, match ($type) {
                RatedType::Movie => 'release_date',
                RatedType::Tv => 'first_air_date',
                RatedType::Episode => 'air_date',
            }),
            'poster_path' => F::string($title, 'poster_path'),
            'backdrop_path' => F::string($title, 'backdrop_path'),
            'still_path' => $episode ? F::string($item, 'still_path') : null,
            'vote_average' => F::float($item, 'vote_average'),
        ];
    }

    /**
     * What "Mes avis" shows of a title rated in Vixeo, from TMDb (cached). Empty when TMDb can't
     * say: the next refresh fills it.
     *
     * @return array<string, mixed>
     */
    private function describe(RatingTarget $target): array
    {
        try {
            $title = $this->tmdb->get($target->type === RatedType::Movie ? "movie/{$target->id}" : "tv/{$target->id}");
            $episode = $target->type === RatedType::Episode ? $this->tmdb->get($target->path) : null;
        } catch (Throwable) {
            return ['title' => ''];
        }

        return [
            'title' => (string) (F::string($title, $target->type === RatedType::Movie ? 'title' : 'name') ?? ''),
            'episode_name' => $episode === null ? null : F::string($episode, 'name'),
            'date' => F::string($episode ?? $title, match ($target->type) {
                RatedType::Movie => 'release_date',
                RatedType::Tv => 'first_air_date',
                RatedType::Episode => 'air_date',
            }),
            'poster_path' => F::string($title, 'poster_path'),
            'backdrop_path' => F::string($title, 'backdrop_path'),
            'still_path' => $episode === null ? null : F::string($episode, 'still_path'),
            'vote_average' => F::float($episode ?? $title, 'vote_average'),
        ];
    }
}
