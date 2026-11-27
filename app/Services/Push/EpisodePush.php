<?php

namespace App\Services\Push;

use App\Enums\ItemType;
use App\Models\EpisodeNotification;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\PushDevice;
use App\Models\User;
use App\Services\Tmdb\AgeGate;
use App\Services\Tmdb\TmdbFormat;
use App\Services\Tmdb\UpcomingEpisodes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * New-episode push notifications (docs/notifications.md), run by `vixeo:push-episodes` every
 * 15 minutes: for each profile whose app wants them, the episodes of its « À voir » shows
 * that came out in the last LOOKBACK_HOURS hours and were not announced yet, one notification
 * per show. Never an episode out before the show was added or the alerts turned on, never at
 * night on the device (the notification waits for 08:00), never twice.
 */
class EpisodePush
{
    /** How far back an episode out is still announced (cron missed, night, FCM down). */
    public const LOOKBACK_HOURS = 48;

    /** Night on the device: from 23:00 to 08:00. */
    public const NIGHT_FROM = 23;

    public const NIGHT_UNTIL = 8;

    public function __construct(
        private PushDevices $devices,
        private UpcomingEpisodes $episodes,
    ) {}

    /**
     * @return array{profiles: int, notifications: int, sent: int, failed: int, removed: int, waiting: int, lines: list<string>}
     */
    public function run(?User $user = null, bool $dryRun = false): array
    {
        $report = ['profiles' => 0, 'notifications' => 0, 'sent' => 0, 'failed' => 0, 'removed' => 0, 'waiting' => 0, 'lines' => []];
        $now = CarbonImmutable::now('UTC');
        $from = $now->subHours(self::LOOKBACK_HOURS);

        /** @var Collection<int, Collection<int, PushDevice>> $byProfile */
        $byProfile = PushDevice::query()
            ->where('new_episodes', true)
            ->whereNotNull('profile_id')
            ->when($user !== null, fn ($query) => $query->where('user_id', $user?->id))
            ->with('profile')
            ->get()
            ->filter(fn (PushDevice $device) => $device->profile !== null)
            ->groupBy('profile_id');
        if ($byProfile->isEmpty()) {
            return $report;
        }

        // The shows of each profile's « À voir », with when they were added.
        $favorites = [];
        ProfileItem::query()
            ->whereIn('profile_id', $byProfile->keys()->all())
            ->where('source', 'tmdb')
            ->where('type', ItemType::Tv)
            ->where('is_favorite', true)
            ->get(['profile_id', 'item_id', 'favorited_at'])
            ->each(function (ProfileItem $item) use (&$favorites) {
                if (preg_match('/^tv:(\d+)$/', (string) $item->item_id, $m) === 1) {
                    $favorites[$item->profile_id][(int) $m[1]] = $item->favorited_at;
                }
            });
        $showIds = array_values(array_unique(array_merge([], ...array_map('array_keys', array_values($favorites)))));
        if ($showIds === []) {
            return $report;
        }

        $released = $this->episodes->availableBetween($showIds, $from, $now);
        if ($released === []) {
            return $report;
        }

        $announced = [];
        EpisodeNotification::query()
            ->whereIn('profile_id', array_keys($favorites))
            ->whereIn('show_id', array_keys($released))
            ->get(['profile_id', 'show_id', 'season_number', 'episode_number'])
            ->each(function (EpisodeNotification $n) use (&$announced) {
                $announced[self::announcedKey($n->profile_id, $n->show_id, $n->season_number, $n->episode_number)] = true;
            });

        foreach ($byProfile as $profileId => $devices) {
            /** @var Profile $profile */
            $profile = $devices->first()->profile;
            $since = $devices->min('new_episodes_since');
            $since = $since === null ? $from : CarbonImmutable::instance($since);

            $pending = [];
            foreach ($favorites[$profileId] ?? [] as $showId => $favoritedAt) {
                $entry = $released[$showId] ?? null;
                if ($entry === null) {
                    continue;
                }
                $start = max($from, $since, $favoritedAt === null ? $from : CarbonImmutable::instance($favoritedAt));
                $episodes = array_values(array_filter(
                    $entry['episodes'],
                    fn (array $e) => $e['available_at']->gte($start) && ! isset($announced[self::announcedKey(
                        $profileId,
                        $showId,
                        (int) ($e['episode']['season_number'] ?? 0),
                        (int) ($e['episode']['episode_number'] ?? 0),
                    )]),
                ));
                if ($episodes !== []) {
                    $pending[$showId] = ['show' => $entry['show'], 'episodes' => $episodes];
                }
            }
            if ($pending === []) {
                continue;
            }
            $report['profiles']++;

            // The device used last says what time it is for the profile.
            $offset = (int) $devices->sortByDesc(fn (PushDevice $d) => $d->last_seen_at?->getTimestamp() ?? 0)->first()->utc_offset;
            $hour = (int) $now->addMinutes($offset)->format('G');
            if ($hour >= self::NIGHT_FROM || $hour < self::NIGHT_UNTIL) {
                $report['waiting'] += count($pending);
                $report['lines'][] = sprintf('%s : %d notification(s) en attente (nuit sur l\'appareil)', $profile->name, count($pending));

                continue;
            }

            foreach ($pending as $showId => $item) {
                $message = self::message($showId, $item['show'], array_column($item['episodes'], 'episode'));
                $report['notifications']++;
                $report['lines'][] = sprintf('%s : %s, %s', $profile->name, $message->title, $message->body);
                if ($dryRun) {
                    continue;
                }

                $result = $this->devices->send($devices->values(), $message);
                $report['sent'] += $result['sent'];
                $report['failed'] += $result['failed'];
                $report['removed'] += $result['removed'];
                if ($result['removed'] > 0) {
                    $devices = PushDevice::whereKey($devices->modelKeys())->get();
                }
                if ($result['sent'] > 0) {
                    EpisodeNotification::insertOrIgnore(array_map(fn (array $e) => [
                        'profile_id' => $profileId,
                        'show_id' => $showId,
                        'season_number' => (int) ($e['episode']['season_number'] ?? 0),
                        'episode_number' => (int) ($e['episode']['episode_number'] ?? 0),
                        'available_at' => $e['available_at'],
                        'sent_at' => $now,
                    ], $item['episodes']));
                }
                if ($devices->isEmpty()) {
                    break;
                }
            }
        }

        return $report;
    }

    /**
     * One notification for these episodes of a show: « S2 · É3 « Le Pont » est disponible. »,
     * « 8 nouveaux épisodes de la saison 2 sont disponibles. ». A tap opens the show.
     *
     * @param  array<string, mixed>  $show
     * @param  list<array<string, mixed>>  $episodes
     */
    public static function message(int $showId, array $show, array $episodes): PushMessage
    {
        if (count($episodes) === 1) {
            $episode = $episodes[0];
            $code = __('S:season · E:episode', [
                'season' => (int) ($episode['season_number'] ?? 0),
                'episode' => (int) ($episode['episode_number'] ?? 0),
            ]);
            $title = self::episodeTitle($episode);
            $body = $title === null
                ? __(':code is available.', ['code' => $code])
                : __(':code “:title” is available.', ['code' => $code, 'title' => $title]);
        } else {
            $seasons = array_values(array_unique(array_map(fn (array $e) => (int) ($e['season_number'] ?? 0), $episodes)));
            $body = count($seasons) === 1
                ? __(':count new episodes of season :season are available.', ['count' => count($episodes), 'season' => $seasons[0]])
                : __(':count new episodes are available.', ['count' => count($episodes)]);
        }

        return new PushMessage(
            title: (string) (TmdbFormat::string($show, 'name') ?? __('New episode')),
            body: $body,
            path: "/titre/tv/{$showId}",
            channel: PushMessage::EPISODES,
            tag: "tv-{$showId}",
            image: TmdbFormat::image(TmdbFormat::string($show, 'backdrop_path'), 'w780'),
        );
    }

    /** The episode's name, unless it only says "Épisode 3". @param  array<string, mixed>  $episode */
    private static function episodeTitle(array $episode): ?string
    {
        $name = trim((string) (TmdbFormat::string($episode, 'name') ?? ''));

        return $name === '' || preg_match('/^(é|e)pisode\s*\d+$/iu', $name) === 1 ? null : $name;
    }

    private static function announcedKey(int $profileId, int $showId, int $season, int $episode): string
    {
        return "{$profileId}|{$showId}|{$season}|{$episode}";
    }
}
