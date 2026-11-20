<?php

namespace App\Services\Tmdb;

use Carbon\CarbonImmutable;
use DateTimeZone;
use ValueError;

/**
 * Episodes coming out soon in some TV shows: what the apps' "nouvel épisode" notifications
 * announce, for the shows of « Ma liste » (docs/notifications.md). Reads each show's details (the
 * cache of TitleSummaries) and the season of its next episode (cached a day, like the title page).
 *
 * TMDb only gives a date (`air_date`, in the show's country). The time is a guess made not to be
 * early: streaming platforms publish worldwide at 08:00 UTC (midnight in California), other
 * channels at 21:00 in the show's country (prime time).
 */
class UpcomingEpisodes
{
    /** TMDb networks that publish worldwide at once: Netflix, Prime Video, Disney+, Apple TV+, Hulu, HBO Max, Paramount+, Peacock. */
    private const STREAMING = [213, 1024, 2739, 2552, 453, 3186, 4330, 3353];

    /** Time zone of the TV channels, for countries that have several (or whose first one is not the capital's). */
    private const ZONES = [
        'US' => 'America/New_York',
        'CA' => 'America/Toronto',
        'MX' => 'America/Mexico_City',
        'BR' => 'America/Sao_Paulo',
        'AR' => 'America/Argentina/Buenos_Aires',
        'CL' => 'America/Santiago',
        'AU' => 'Australia/Sydney',
        'NZ' => 'Pacific/Auckland',
        'RU' => 'Europe/Moscow',
        'ES' => 'Europe/Madrid',
        'PT' => 'Europe/Lisbon',
        'DE' => 'Europe/Berlin',
        'GB' => 'Europe/London',
        'CN' => 'Asia/Shanghai',
        'ID' => 'Asia/Jakarta',
        'UA' => 'Europe/Kyiv',
        'KZ' => 'Asia/Almaty',
    ];

    public function __construct(private TmdbClient $tmdb, private TitleSummaries $summaries) {}

    /**
     * Episodes of these TV shows available between [$from] and [$to], by show (the push of new
     * episodes, App\Services\Push\EpisodePush). Reads the season of the last episode out and
     * of the next one: TMDb moves an episode from `next_episode_to_air` to `last_episode_to_air`
     * once it is out, and the show details are cached a day. Age limits are left to the caller.
     *
     * @param  list<int>  $showIds
     * @return array<int, array{show: array<string, mixed>, episodes: list<array{episode: array<string, mixed>, available_at: CarbonImmutable}>}>
     */
    public function availableBetween(array $showIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $shows = [];
        $seasons = [];
        $known = [];
        foreach ($this->summaries->shows($showIds) as $id => $show) {
            if ($show === null) {
                continue;
            }
            foreach (['last_episode_to_air', 'next_episode_to_air'] as $key) {
                $episode = $show[$key] ?? null;
                $at = is_array($episode) ? self::availableAt($show, TmdbFormat::string($episode, 'air_date')) : null;
                if ($at === null || $at->lt($from) || $at->gt($to)) {
                    continue;
                }
                $shows[$id] = $show;
                $known[$id][] = $episode;
                $path = "tv/{$id}/season/".(int) ($episode['season_number'] ?? 0);
                $seasons[$path] = $id;
            }
        }
        $responses = $seasons === [] ? [] : $this->tmdb->getMany(array_keys($seasons));

        $episodes = [];
        foreach ($seasons as $path => $id) {
            $list = TmdbFormat::array($responses[$path] ?? [], 'episodes');
            foreach ($list as $episode) {
                if (is_array($episode)) {
                    $episodes[$id][self::key($episode)] = $episode;
                }
            }
        }

        $result = [];
        foreach ($shows as $id => $show) {
            // Season unavailable: at least the episodes the show's details give.
            $list = $episodes[$id] ?? [];
            foreach ($known[$id] ?? [] as $episode) {
                $list[self::key($episode)] ??= $episode;
            }
            $available = [];
            foreach ($list as $episode) {
                $at = self::availableAt($show, TmdbFormat::string($episode, 'air_date'));
                if ($at !== null && $at->gte($from) && $at->lte($to)) {
                    $available[] = ['episode' => $episode, 'available_at' => $at];
                }
            }
            usort($available, fn (array $a, array $b) => self::key($a['episode']) <=> self::key($b['episode']));
            if ($available !== []) {
                $result[$id] = ['show' => $show, 'episodes' => $available];
            }
        }

        return $result;
    }

    /** Season and number, to sort and to find an episode again. @param  array<string, mixed>  $episode */
    private static function key(array $episode): string
    {
        return sprintf('%04d-%05d', (int) ($episode['season_number'] ?? 0), (int) ($episode['episode_number'] ?? 0));
    }

    /**
     * When an episode aired on that date should be available (see the class).
     *
     * @param  array<string, mixed>  $show  TMDb details
     */
    public static function availableAt(array $show, ?string $airDate): ?CarbonImmutable
    {
        if ($airDate === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $airDate) !== 1) {
            return null;
        }

        $networks = array_map(fn (mixed $network) => is_array($network) ? (int) ($network['id'] ?? 0) : 0, TmdbFormat::array($show, 'networks'));
        if (array_intersect($networks, self::STREAMING) !== []) {
            return CarbonImmutable::parse("{$airDate} 08:00:00", 'UTC');
        }

        return CarbonImmutable::parse("{$airDate} 21:00:00", self::zone(TmdbFormat::array($show, 'origin_country')[0] ?? null))->utc();
    }

    /** Time zone of a country's TV channels (UTC when unknown). */
    private static function zone(mixed $country): string
    {
        if (! is_string($country) || preg_match('/^[A-Za-z]{2}$/', $country) !== 1) {
            return 'UTC';
        }
        $country = strtoupper($country);
        if (isset(self::ZONES[$country])) {
            return self::ZONES[$country];
        }

        try {
            $zones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country);
        } catch (ValueError) {
            return 'UTC';
        }

        return $zones[0] ?? 'UTC';
    }
}
