<?php

namespace App\Services;

use App\Enums\RatedType;
use App\Models\Profile;
use App\Models\TmdbRating;
use App\Services\Social\Activities;
use App\Services\Tmdb\RatingTarget;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The profile's reviews (« Mes avis », docs/avis.md): a rating and an optional comment per movie,
 * TV show or episode. They live in Vixeo only: nothing is sent to or read from a TMDb account.
 * TMDb only gives what « Mes avis » shows of the title (name, images, date). A new review marks
 * its movie, TV show or episode watched (developer's request, 23/11/2026).
 */
class TmdbRatings
{
    /** Reviews without their title (saved while TMDb was down) completed per listing, at most. */
    private const DESCRIBE_PER_LISTING = 10;

    public function __construct(private TmdbClient $tmdb, private Activities $activities, private ProfileSync $sync) {}

    /**
     * Every review of the profile, the most recent first.
     *
     * @return Collection<int, TmdbRating>
     */
    public function all(Profile $profile): Collection
    {
        // With their likes (« J'aime » of other profiles, docs/social.md).
        $rows = $this->query($profile)->withCount('likes')->latest('rated_at')->latest('id')->get();

        // Saved while TMDb was unreachable: what « Mes avis » shows of the title, now (a few per
        // listing, so a TMDb outage never makes the page slow).
        foreach ($rows->where('title', '')->take(self::DESCRIBE_PER_LISTING) as $row) {
            $row->fill($this->describe(RatingTarget::fromPath($row->target)));
            if ($row->title !== '') {
                $row->save();
            }
        }

        return $rows;
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
     * Saves a review. [$comment] is only changed when given (null removes it), [$spoilers] too.
     * A new review, or a new comment, goes to the feed of the profile's followers; a new review
     * marks its title watched.
     */
    public function save(Profile $profile, RatingTarget $target, float $value, bool $withComment, ?string $comment, ?bool $spoilers = null): TmdbRating
    {
        $row = $this->query($profile)->firstOrNew(['target' => $target->path]);
        $fresh = ! $row->exists;
        $previousComment = $row->comment;
        if (! $row->exists || $row->value !== $value) {
            $row->rated_at = now();
        }
        if ($spoilers !== null) {
            $row->contains_spoilers = $spoilers;
        }
        $row->fill([
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

        // Watched first: the feed shows the review, not « a vu » next to it.
        if ($fresh) {
            $this->markWatched($profile, $row);
        }
        $newComment = $row->comment !== null && $row->comment !== $previousComment;
        if (! $profile->is_kid && ($fresh || $newComment)) {
            $this->activities->review($row);
        }

        return $row;
    }

    /**
     * A new review marks its movie, TV show or episode watched, through the synchronisation (as
     * the « Vu » button or the player would: out of « Continuer à regarder », in the diary and
     * the feed), unless it already is.
     */
    private function markWatched(Profile $profile, TmdbRating $row): void
    {
        $itemId = match ($row->type) {
            RatedType::Movie => "movie:{$row->tmdb_id}",
            RatedType::Tv => "tv:{$row->tmdb_id}",
            RatedType::Episode => "episode:{$row->tmdb_id}:{$row->season_number}:{$row->episode_number}",
        };
        $watched = $profile->items()->where('source', 'tmdb')->where('item_id', $itemId)->where('is_watched', true)->exists();
        if ($watched) {
            return;
        }

        $at = now()->toIso8601String();
        $change = [
            'source' => 'tmdb',
            'item_id' => $itemId,
            'type' => $row->type->value,
            'title' => (string) $row->title,
            'poster_url' => F::image($row->poster_path === null ? null : (string) $row->poster_path, 'w342'),
            'backdrop_url' => F::image($row->backdrop_path === null ? null : (string) $row->backdrop_path, 'w1280'),
            'watched' => ['value' => true, 'at' => $at],
        ];
        if ($row->type === RatedType::Episode) {
            $change += ['parent_item_id' => "tv:{$row->tmdb_id}", 'season_number' => $row->season_number, 'episode_number' => $row->episode_number];
        } else {
            $change['year'] = F::year($row->date === null ? null : (string) $row->date);
        }
        if ($row->type !== RatedType::Tv) {
            $change['progress'] = ['position_ms' => 0, 'continue_watching' => false, 'at' => $at];
        }
        $this->sync->push($profile, [$change]);
    }

    /** Removes a review, and its comment. */
    public function remove(Profile $profile, RatingTarget $target): void
    {
        $this->query($profile)->where('target', $target->path)->delete();
    }

    /** @return Builder<TmdbRating> */
    private function query(Profile $profile): Builder
    {
        return TmdbRating::query()->where('profile_id', $profile->id);
    }

    /**
     * What « Mes avis » shows of a reviewed title, from TMDb (cached). Empty title when TMDb
     * can't say: the next listing fills it.
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
