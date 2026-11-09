<?php

namespace App\Services\Tmdb;

use App\Enums\RatedType;
use App\Models\TmdbLink;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

/**
 * A person's TMDb account (docs/compte-tmdb.md): sign-in with a request token they approve on
 * themoviedb.org, then their ratings, with the session of their link. Never cached.
 */
class TmdbAccount
{
    /** TMDb status codes. */
    private const INVALID_API_KEY = 7;

    private const INVALID_REQUEST_TOKEN = 33;

    public function __construct(private TmdbClient $tmdb) {}

    /**
     * A request token for the person to approve on themoviedb.org.
     *
     * @return array{request_token: string, expires_at: CarbonImmutable}
     */
    public function requestToken(): array
    {
        $response = $this->check($this->tmdb->send('get', 'authentication/token/new'));
        $token = $response->json('request_token');

        if (! is_string($token) || $token === '') {
            throw new TmdbUnavailableException;
        }

        try {
            $expiresAt = CarbonImmutable::parse((string) $response->json('expires_at'));
        } catch (Throwable) {
            $expiresAt = CarbonImmutable::now()->addHour();
        }

        return ['request_token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Session of an approved request token. Null while the person hasn't approved it yet.
     *
     * @throws TmdbRequestTokenExpired when the token expired or was already used
     */
    public function createSession(string $requestToken): ?string
    {
        $response = $this->tmdb->send('post', 'authentication/session/new', body: ['request_token' => $requestToken]);

        if ($response->unauthorized() || $response->forbidden()) {
            return match ((int) $response->json('status_code')) {
                self::INVALID_API_KEY => throw $this->credentialsRejected(),
                self::INVALID_REQUEST_TOKEN => throw new TmdbRequestTokenExpired,
                default => null, // not approved yet
            };
        }

        $session = $this->check($response)->json('session_id');

        return is_string($session) && $session !== '' ? $session : throw new TmdbUnavailableException;
    }

    /**
     * The account of a session: id, username, name, avatar.
     *
     * @return array<string, mixed>
     */
    public function account(string $session): array
    {
        return $this->check($this->tmdb->send('get', 'account', ['session_id' => $session]))->json() ?? [];
    }

    /** Ends a session on TMDb. Best effort: a failure changes nothing for the person. */
    public function deleteSession(string $session): void
    {
        try {
            $this->tmdb->send('delete', 'authentication/session', body: ['session_id' => $session]);
        } catch (Throwable) {
            Log::info('TMDb session not deleted');
        }
    }

    /** The profile's rating of a movie or TV show (0.5–10), null when not rated. */
    public function rating(TmdbLink $link, RatingTarget $target): ?float
    {
        $rated = $this->get($link, $target->path.'/account_states')['rated'] ?? false;

        return is_array($rated) && isset($rated['value']) ? (float) $rated['value'] : null;
    }

    /**
     * The profile's ratings of a season's episodes.
     *
     * @return array<int, float> episode number => rating
     */
    public function seasonRatings(TmdbLink $link, int $showId, int $season): array
    {
        $ratings = [];
        foreach ((array) ($this->get($link, "tv/{$showId}/season/{$season}/account_states")['results'] ?? []) as $episode) {
            $rated = is_array($episode) ? ($episode['rated'] ?? false) : false;
            if (is_array($rated) && isset($rated['value'], $episode['episode_number'])) {
                $ratings[(int) $episode['episode_number']] = (float) $rated['value'];
            }
        }
        ksort($ratings);

        return $ratings;
    }

    public function rate(TmdbLink $link, RatingTarget $target, float $value): void
    {
        $this->check($this->tmdb->send('post', $target->path.'/rating', ['session_id' => $link->session_id], ['value' => $value]), $link);
    }

    public function unrate(TmdbLink $link, RatingTarget $target): void
    {
        $response = $this->tmdb->send('delete', $target->path.'/rating', ['session_id' => $link->session_id]);

        // Already not rated: nothing to remove (TMDb answers 404 with "resource not found").
        if (! $response->notFound()) {
            $this->check($response, $link);
        }
    }

    /**
     * A page of the account's ratings, the most recent first.
     *
     * @return array{page: int, results: list<array<string, mixed>>, total_pages: int, total_results: int}
     */
    public function rated(TmdbLink $link, RatedType $type, int $page): array
    {
        $data = $this->get($link, $type->ratedPath($link->account_id), ['page' => $page, 'sort_by' => 'created_at.desc']);

        return [
            'page' => (int) ($data['page'] ?? $page),
            'results' => array_values(array_filter((array) ($data['results'] ?? []), 'is_array')),
            'total_pages' => (int) ($data['total_pages'] ?? 0),
            'total_results' => (int) ($data['total_results'] ?? 0),
        ];
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function get(TmdbLink $link, string $path, array $query = []): array
    {
        return $this->check($this->tmdb->send('get', $path, ['session_id' => $link->session_id] + $query), $link)->json() ?? [];
    }

    /** Turns TMDb errors into ours; a session TMDb refuses deletes the link. */
    private function check(Response $response, ?TmdbLink $link = null): Response
    {
        if ($response->successful()) {
            return $response;
        }

        if ($response->unauthorized()) {
            if ((int) $response->json('status_code') === self::INVALID_API_KEY || $link === null) {
                throw $this->credentialsRejected();
            }
            $link->delete();

            throw TmdbLinkRevoked::revoked();
        }

        if ($response->notFound()) {
            throw new NotFoundHttpException;
        }

        if ($response->clientError() && ! $response->tooManyRequests()) {
            Log::warning('TMDb refused an account request', ['status' => $response->status(), 'code' => $response->json('status_code')]);

            throw new UnprocessableEntityHttpException('TMDb refused this request.');
        }

        Log::warning('TMDb account request failed', ['status' => $response->status()]);

        throw new TmdbUnavailableException;
    }

    private function credentialsRejected(): TmdbUnavailableException
    {
        Log::error('TMDb rejected our credentials: check TMDB_READ_ACCESS_TOKEN / TMDB_API_KEY.');

        return new TmdbUnavailableException;
    }
}
