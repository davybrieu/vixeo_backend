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
 * The profile's reviews (« Mes avis », docs/avis.md): a rating and an optional comment per movie
 * or TV show (episodes are not rated). They live in Vixeo only: nothing is sent to or read from a TMDb account.
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

    /** The review of one title, null when there is none. */
    public function find(Profile $profile, RatingTarget $target): ?TmdbRating
    {
        return $this->query($profile)->where('target', $target->path)->first();
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
        $movie = $target->type === RatedType::Movie;
        try {
            $title = $this->tmdb->get($target->path);
        } catch (Throwable) {
            return ['title' => ''];
        }

        return [
            'title' => (string) (F::string($title, $movie ? 'title' : 'name') ?? ''),
            'date' => F::string($title, $movie ? 'release_date' : 'first_air_date'),
            'poster_path' => F::string($title, 'poster_path'),
            'backdrop_path' => F::string($title, 'backdrop_path'),
            'vote_average' => F::float($title, 'vote_average'),
        ];
    }
}
