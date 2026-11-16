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
use App\Services\Tmdb\TmdbLinkRevoked;
use App\Services\Tmdb\TmdbUnavailableException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * The profile's reviews (« Mes avis », docs/compte-tmdb.md): Vixeo owns them, with or without a
 * TMDb account. When one is linked, TMDb follows Vixeo: each review written here is sent there,
 * and linking the account sends every review (Vixeo wins when TMDb has another rating for the same
 * title). Ratings given on themoviedb.org that Vixeo doesn't have are brought in, at most every 10
 * minutes; a rating changed or removed there doesn't change Vixeo's review.
 */
class TmdbRatings
{
    /** Seconds before the account is read again from TMDb. */
    public const STALE_AFTER = 600;

    /** Pages of 20 ratings read per type, at most. */
    private const MAX_PAGES = 50;

    /** Days a removal that could not reach TMDb is retried. */
    private const REMOVALS_TTL_DAYS = 30;

    public function __construct(private TmdbAccount $account, private TmdbClient $tmdb) {}

    /**
     * Every review of the profile, the most recent first. With a linked account, synced first
     * (at most every 10 minutes).
     *
     * @return Collection<int, TmdbRating>
     */
    public function all(Profile $profile, ?TmdbLink $link): Collection
    {
        if ($link !== null) {
            $this->syncIfStale($profile, $link);
        }

        return $this->query($profile)->latest('rated_at')->latest('id')->get();
    }

    /** The review of one title or episode, null when there is none. */
    public function find(Profile $profile, RatingTarget $target): ?TmdbRating
    {
        return $this->query($profile)->where('target', $target->path)->first();
    }

    /**
     * The reviews of a season's episodes, by episode number.
     *
     * @return Collection<int, TmdbRating>
     */
    public function season(Profile $profile, int $showId, int $season): Collection
    {
        return $this->query($profile)
            ->where('type', RatedType::Episode)
            ->where('tmdb_id', $showId)
            ->where('season_number', $season)
            ->get()
            ->keyBy('episode_number');
    }

    /**
     * Saves a review written in Vixeo, then sends its rating to the linked TMDb account, if any.
     * [$comment] is only changed when given (null removes it). TMDb unreachable: the review is
     * saved and sent at the next sync.
     */
    public function save(Profile $profile, ?TmdbLink $link, RatingTarget $target, float $value, bool $withComment, ?string $comment): TmdbRating
    {
        $row = $this->query($profile)->firstOrNew(['target' => $target->path]);
        if (! $row->exists || $row->value !== $value) {
            $row->rated_at = now();
        }
        $row->fill([
            // Not on TMDb yet.
            'account_id' => null,
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

        if ($link !== null && $this->send(fn () => $this->account->rate($link, $target, $value))) {
            $row->forceFill(['account_id' => $link->account_id])->save();
        }

        return $row;
    }

    /**
     * Removes a review (and its comment), and its rating from the linked TMDb account, if any.
     * TMDb unreachable: the removal is retried at the next syncs.
     */
    public function remove(Profile $profile, ?TmdbLink $link, RatingTarget $target): void
    {
        $this->query($profile)->where('target', $target->path)->delete();

        if ($link !== null && ! $this->send(fn () => $this->account->unrate($link, $target))) {
            $removals = $this->removals($profile);
            $removals[$target->path] = true;
            Cache::put(self::removalsKey($profile), $removals, now()->addDays(self::REMOVALS_TTL_DAYS));
        }
    }

    /** The account was unlinked: the reviews stay, they are no longer on any account. */
    public function unlinked(Profile $profile): void
    {
        $this->query($profile)->update(['account_id' => null]);
        Cache::forget(self::removalsKey($profile));
    }

    /** Syncs with the account, unless done in the last 10 minutes. */
    public function syncIfStale(Profile $profile, TmdbLink $link): void
    {
        $key = "tmdb-ratings:{$profile->id}:{$link->account_id}";
        if (Cache::has($key)) {
            return;
        }

        try {
            $this->sync($profile, $link);
            Cache::put($key, true, self::STALE_AFTER);
        } catch (Throwable $e) {
            // TMDb down, the account no longer linked…: the reviews are shown as they are.
            Log::warning('TMDb ratings not synced', ['error' => $e::class]);
        }
    }

    /**
     * Syncs the reviews with the TMDb account. TMDb follows Vixeo: the reviews not sent to this
     * account yet are sent (all of them that differ, [$all], when the account was just linked);
     * the removals that could not reach TMDb are sent again. Ratings TMDb has and Vixeo doesn't
     * (given on themoviedb.org) are brought in.
     *
     * @throws TmdbUnavailableException|TmdbLinkRevoked
     */
    public function sync(Profile $profile, TmdbLink $link, bool $all = false): void
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

        // Removed in Vixeo while TMDb was unreachable.
        $removals = $this->removals($profile);
        foreach (array_keys($removals) as $path) {
            if (! isset($remote[$path]) || $this->send(fn () => $this->account->unrate($link, RatingTarget::fromPath($path)))) {
                unset($removals[$path]);
            }
            unset($remote[$path]);
        }
        $removals === []
            ? Cache::forget(self::removalsKey($profile))
            : Cache::put(self::removalsKey($profile), $removals, now()->addDays(self::REMOVALS_TTL_DAYS));

        // Vixeo's reviews: sent to TMDb when it doesn't have them yet (Vixeo wins).
        $local = $this->query($profile)->get()->keyBy('target');
        foreach ($local as $path => $row) {
            $theirs = $remote[$path]['value'] ?? null;
            $send = $all ? $theirs === null || (float) $theirs !== (float) $row->value : $row->account_id !== $link->account_id;
            if ($send && ! $this->send(fn () => $this->account->rate($link, RatingTarget::fromPath($path), (float) $row->value))) {
                continue;
            }
            if ($row->account_id !== $link->account_id) {
                $row->forceFill(['account_id' => $link->account_id]);
            }
            // Saved while TMDb was unreachable: what « Mes avis » shows of the title.
            if ($row->title === '') {
                $row->fill(isset($remote[$path]) ? Arr::except($remote[$path], ['target', 'value']) : $this->describe(RatingTarget::fromPath($path)));
            }
            $row->save();
        }

        // Given on themoviedb.org: brought in. TMDb gives no date: they keep TMDb's order.
        DB::transaction(function () use ($profile, $link, $remote, $local) {
            $at = now();
            foreach ($remote as $path => $attributes) {
                if ($local->has($path)) {
                    continue;
                }
                $row = new TmdbRating($attributes + ['account_id' => $link->account_id, 'rated_at' => $at]);
                $row->profile()->associate($profile)->save();
                $at = $at->copy()->subSecond();
            }
        });
    }

    /**
     * Runs a call to the TMDb account: false when TMDb is unreachable, refuses it or no longer
     * accepts the account (then unlinked by TmdbAccount).
     */
    private function send(callable $call): bool
    {
        try {
            $call();

            return true;
        } catch (TmdbUnavailableException|TmdbLinkRevoked|HttpException $e) {
            Log::warning('TMDb rating not sent', ['error' => $e::class]);

            return false;
        }
    }

    /** @return array<string, true> paths of the reviews removed while TMDb was unreachable */
    private function removals(Profile $profile): array
    {
        $removals = Cache::get(self::removalsKey($profile));

        return is_array($removals) ? $removals : [];
    }

    private static function removalsKey(Profile $profile): string
    {
        return "tmdb-ratings:{$profile->id}:removals";
    }

    /** @return Builder<TmdbRating> */
    private function query(Profile $profile): Builder
    {
        return TmdbRating::query()->where('profile_id', $profile->id);
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
