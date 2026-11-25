<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Http\Middleware\ResolveProfile;
use App\Models\Profile;
use App\Models\TitleArtwork;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * « Affiches, arrière-plans, logos » (developer's request, 25/11/2026, docs/images-des-titres.md):
 * a profile chooses the poster, the backdrop and the logo of a title among TMDb's, and the API
 * gives them instead of TMDb's wherever it gives the title to this profile (lists, search, home,
 * title page: MediaItemResource, MovieResource, ShowResource).
 */
final class TitleArtworks
{
    /** Images of each kind offered, the best first. */
    public const MAX_PER_KIND = 60;

    /** The kinds, and the key of TMDb's `images`. */
    public const KINDS = ['poster' => 'posters', 'backdrop' => 'backdrops', 'logo' => 'logos'];

    /** Languages first, by kind (backdrops: without text first). */
    private const LANGUAGES = [
        'poster' => ['fr', 'en', null],
        'backdrop' => [null, 'fr', 'en'],
        'logo' => ['fr', 'en', null],
    ];

    /** Sizes of the thumbnails of the choice page. */
    private const THUMBNAILS = ['poster' => 'w342', 'backdrop' => 'w780', 'logo' => 'w300'];

    /**
     * What TMDb has for a title (its `images`, appended to the title page by TmdbCatalog), by kind:
     * French, English and without text, the best rated first.
     *
     * @param  array<string, mixed>  $title
     * @return array<string, list<array{path: string, url: string|null, language: string|null, aspect_ratio: float}>>
     */
    public function images(array $title): array
    {
        $images = F::array($title, 'images');
        $offered = [];
        foreach (self::KINDS as $kind => $key) {
            $languages = self::LANGUAGES[$kind];
            $list = array_values(array_filter(
                F::array($images, $key),
                fn ($image) => is_array($image)
                    && is_string($image['file_path'] ?? null)
                    && ! str_ends_with(strtolower($image['file_path']), '.svg')
                    && in_array($image['iso_639_1'] ?? null, $languages, true),
            ));
            usort($list, fn (array $a, array $b) => [
                array_search($a['iso_639_1'] ?? null, $languages, true),
                -(float) ($a['vote_average'] ?? 0),
            ] <=> [
                array_search($b['iso_639_1'] ?? null, $languages, true),
                -(float) ($b['vote_average'] ?? 0),
            ]);
            $offered[$kind] = array_map(fn (array $image) => [
                'path' => (string) $image['file_path'],
                'url' => F::image((string) $image['file_path'], self::THUMBNAILS[$kind]),
                'language' => is_string($image['iso_639_1'] ?? null) ? $image['iso_639_1'] : null,
                'aspect_ratio' => round((float) ($image['aspect_ratio'] ?? 0), 3),
            ], array_slice($list, 0, self::MAX_PER_KIND));
        }

        return $offered;
    }

    /**
     * What the profile of [$request] chose for a title, once per request for all its titles; null:
     * nothing (or no profile).
     *
     * @return array{poster: string|null, backdrop: string|null, logo: string|null, logo_aspect_ratio: float|null}|null
     */
    public static function chosen(Request $request, MediaType $type, int $id): ?array
    {
        $profile = ResolveProfile::profile($request);
        if ($profile === null) {
            return null;
        }

        $all = $request->attributes->get('title-artworks');
        if (! is_array($all)) {
            $all = [];
            foreach (TitleArtwork::query()->where('profile_id', $profile->id)->get() as $artwork) {
                $all[$artwork->media_type.':'.$artwork->tmdb_id] = self::shape($artwork);
            }
            $request->attributes->set('title-artworks', $all);
        }

        return $all[$type->value.':'.$id] ?? null;
    }

    /**
     * The choice of [$profile] for a title, as it is now.
     *
     * @return array{poster: string|null, backdrop: string|null, logo: string|null, logo_aspect_ratio: float|null}
     */
    public function of(Profile $profile, MediaType $type, int $id): array
    {
        $artwork = TitleArtwork::query()
            ->where('profile_id', $profile->id)->where('media_type', $type->value)->where('tmdb_id', $id)
            ->first();

        return $artwork === null ? self::shape(null) : self::shape($artwork);
    }

    /**
     * Changes the kinds given ([$changes]: `poster`, `backdrop`, `logo`, a path of [$offered] or
     * null for TMDb's), the others stay. 422 on a path TMDb doesn't offer for this title.
     *
     * @param  array<string, string|null>  $changes
     * @param  array<string, list<array{path: string, url: string|null, language: string|null, aspect_ratio: float}>>  $offered
     * @return array{poster: string|null, backdrop: string|null, logo: string|null, logo_aspect_ratio: float|null}
     */
    public function choose(Profile $profile, MediaType $type, int $id, array $changes, array $offered): array
    {
        $values = [];
        foreach (array_keys(self::KINDS) as $kind) {
            if (! array_key_exists($kind, $changes)) {
                continue;
            }
            $path = $changes[$kind];
            $image = null;
            if ($path !== null) {
                foreach ($offered[$kind] ?? [] as $candidate) {
                    if ($candidate['path'] === $path) {
                        $image = $candidate;
                    }
                }
                if ($image === null) {
                    throw ValidationException::withMessages([$kind => [__('This image is not one of this title.')]]);
                }
            }
            $values[$kind.'_path'] = $path;
            if ($kind === 'logo') {
                $values['logo_aspect_ratio'] = $image === null ? null : ($image['aspect_ratio'] > 0 ? $image['aspect_ratio'] : 3.0);
            }
        }

        $artwork = TitleArtwork::query()->firstOrNew([
            'profile_id' => $profile->id,
            'media_type' => $type->value,
            'tmdb_id' => $id,
        ]);
        $artwork->fill($values);
        if ($artwork->poster_path === null && $artwork->backdrop_path === null && $artwork->logo_path === null) {
            // All of TMDb again: nothing to keep.
            if ($artwork->exists) {
                $artwork->delete();
            }

            return self::shape(null);
        }
        $artwork->save();

        return self::shape($artwork);
    }

    /** @return array{poster: string|null, backdrop: string|null, logo: string|null, logo_aspect_ratio: float|null} */
    private static function shape(?TitleArtwork $artwork): array
    {
        return [
            'poster' => $artwork?->poster_path,
            'backdrop' => $artwork?->backdrop_path,
            'logo' => $artwork?->logo_path,
            'logo_aspect_ratio' => $artwork?->logo_aspect_ratio,
        ];
    }
}
