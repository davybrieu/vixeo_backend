<?php

namespace App\Services;

use App\Enums\RatedType;
use App\Models\Profile;
use App\Models\TmdbRating;
use App\Services\Tmdb\RatingTarget;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The profile's reviews (« Mes avis », docs/avis.md): a rating and an optional comment per movie,
 * TV show or episode. They live in Vixeo only: nothing is sent to or read from a TMDb account.
 * TMDb only gives what « Mes avis » shows of the title (name, images, date).
 */
class TmdbRatings
{
    /** Reviews without their title (saved while TMDb was down) completed per listing, at most. */
    private const DESCRIBE_PER_LISTING = 10;

    public function __construct(private TmdbClient $tmdb) {}

    /**
     * Every review of the profile, the most recent first.
     *
     * @return Collection<int, TmdbRating>
     */
    public function all(Profile $profile): Collection
    {
        $rows = $this->query($profile)->latest('rated_at')->latest('id')->get();

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

    /** Saves a review. [$comment] is only changed when given (null removes it). */
    public function save(Profile $profile, RatingTarget $target, float $value, bool $withComment, ?string $comment): TmdbRating
    {
        $row = $this->query($profile)->firstOrNew(['target' => $target->path]);
        if (! $row->exists || $row->value !== $value) {
            $row->rated_at = now();
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

        return $row;
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
