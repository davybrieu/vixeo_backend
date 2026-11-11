<?php

namespace App\Services\Tv;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use XMLReader;

/**
 * Programme guide (EPG) of the live channels, read from XMLTV files (the standard format of TV
 * guides, `.xml` or `.xml.gz`) listed on the server (`TV_EPG_URLS`, comma-separated, in order of
 * preference). Provider-neutral: whatever serves the streams, the guide comes from these files.
 *
 * The files are downloaded by `php artisan tv:epg` (scheduled every 6 hours) and parsed into a
 * cached index: each guide channel's names and icon, and its programmes of the next hours, one
 * cache entry per channel (a request only reads the channel it needs). A channel of the provider
 * is matched by its guide id (`epg_channel_id`, from a panel) or by its name ({@see ChannelName});
 * the first file that has programmes for it wins.
 *
 * Without files, or when none can be read, the guide is simply empty: live TV keeps working. The
 * apps never see these addresses: they call `GET /tv/channels/{id}/epg`.
 */
class Epg
{
    private const INDEX_KEY = 'tv:epg:index';

    private const REFRESHING_KEY = 'tv:epg:refreshing';

    /** Hours of programmes kept from the refresh (the guide is refreshed every 6 hours). */
    private const HORIZON_HOURS = 48;

    private const MAX_DESCRIPTION = 1500;

    /** @var array{generation: string, keys: array<string, string>, icons: array<string, string>}|null */
    private ?array $index = null;

    public function configured(): bool
    {
        return $this->sources() !== [];
    }

    /** @return list<string> */
    public function sources(): array
    {
        return array_values(array_filter((array) config('services.tv.epg_urls', []), fn ($url) => is_string($url) && preg_match('#^https?://#i', $url)));
    }

    /**
     * Programmes of a channel, from the one on now, in order.
     *
     * @return list<array{title: string, subtitle: string|null, description: string|null, category: string|null, image: string|null, start: string, end: string}>
     */
    public function programmes(?string $epgChannelId, string $name, int $limit = 30): array
    {
        $index = $this->index();
        $key = $index === null ? null : $this->find($index['keys'], $epgChannelId, $name);
        if ($key === null) {
            return [];
        }

        $stored = Cache::get($this->channelKey($index['generation'], $key));
        $now = time();
        $list = array_values(array_filter(is_array($stored) ? $stored : [], fn (array $p) => $p['end'] > $now));

        return array_map(fn (array $p) => [
            'title' => $p['title'],
            'subtitle' => $p['subtitle'],
            'description' => $p['description'],
            'category' => $p['category'],
            'image' => $p['image'],
            'start' => gmdate('Y-m-d\TH:i:s\Z', $p['start']),
            'end' => gmdate('Y-m-d\TH:i:s\Z', $p['end']),
        ], array_slice($list, 0, $limit));
    }

    /**
     * How a channel with programmes matched the guide: id, name, approximate, or null.
     */
    public function matchMethod(?string $epgChannelId, string $name): ?string
    {
        $index = $this->index();
        if ($index === null) {
            return null;
        }

        $method = null;
        $key = $this->find($index['keys'], $epgChannelId, $name, $method);
        if ($key === null) {
            return null;
        }

        $programmes = Cache::get($this->channelKey($index['generation'], $key));
        $now = time();
        $hasUpcomingProgramme = is_array($programmes)
            && collect($programmes)->contains(fn (array $programme) => $programme['end'] > $now);

        return $hasUpcomingProgramme ? $method : null;
    }

    /**
     * The guide's icon of a channel (its address at the guide's source), or null. `$fallback`: the
     * icon of a shorter name instead ({@see ChannelName::fallbackKeys}).
     */
    public function icon(?string $epgChannelId, string $name, bool $fallback = false): ?string
    {
        $index = $this->index();
        if ($index === null) {
            return null;
        }
        if (! $fallback) {
            return $this->find($index['icons'], $epgChannelId, $name);
        }
        // The icon of a shorter name (logos only: never the programmes of another channel).
        foreach (ChannelName::fallbackKeys($name) as $key) {
            if (isset($index['icons'][$key])) {
                return $index['icons'][$key];
            }
        }

        return null;
    }

    /**
     * Downloads every file and replaces the cached guide (`php artisan tv:epg`). A file that can't
     * be read is skipped; when none can, the previous guide is kept.
     *
     * @return list<array{url: string, channels: int, programmes: int, error: string|null}>
     */
    public function refresh(): array
    {
        $report = [];
        $sources = [];
        foreach ($this->sources() as $i => $url) {
            try {
                $sources[$i] = $this->parse($this->download($url));
                $report[] = ['url' => $url, 'channels' => count($sources[$i]['channels']), 'programmes' => array_sum(array_map('count', $sources[$i]['programmes'])), 'error' => null];
            } catch (Throwable $e) {
                Log::warning('EPG source unavailable', ['url' => $url, 'error' => $e->getMessage()]);
                $report[] = ['url' => $url, 'channels' => 0, 'programmes' => 0, 'error' => $e->getMessage()];
            }
        }

        if ($sources === []) {
            // Nothing readable: keep the previous guide, or remember the failure for 10 minutes so
            // the files are not fetched again on every request.
            Cache::add(self::INDEX_KEY, ['generation' => 'none', 'keys' => [], 'icons' => []], 600);

            return $report;
        }

        $this->store($sources);

        return $report;
    }

    /**
     * @param  array<int, array{channels: array<string, array{names: list<string>, icon: string|null}>, programmes: array<string, list<array<string, mixed>>>}>  $sources
     */
    private function store(array $sources): void
    {
        $generation = bin2hex(random_bytes(6));
        $ttl = (int) config('services.tv.epg_ttl', 43200);
        $keys = [];
        $icons = [];
        $entries = [];

        // A name (guide id, or name key) goes to the first file that has programmes for it, and
        // to the first file that has an icon for it: files are listed in order of preference.
        foreach ($sources as $s => $source) {
            foreach ($source['channels'] as $id => $channel) {
                $names = array_filter(array_unique(['id:'.$id, ...array_map(ChannelName::key(...), [$id, ...$channel['names']])]), fn (string $n) => $n !== '');
                $list = $source['programmes'][$id] ?? [];
                foreach ($names as $name) {
                    if ($list !== []) {
                        $keys[$name] ??= $s.':'.$id;
                    }
                    if ($channel['icon'] !== null) {
                        $icons[$name] ??= $channel['icon'];
                    }
                }
                if ($list !== []) {
                    usort($list, fn (array $a, array $b) => $a['start'] <=> $b['start']);
                    $entries[$this->channelKey($generation, $s.':'.$id)] = $list;
                }
            }
        }

        foreach (array_chunk($entries, 200, true) as $chunk) {
            Cache::putMany($chunk, $ttl + 3600);
        }
        // The index last: a request never sees a generation whose programmes are not stored yet.
        Cache::put(self::INDEX_KEY, ['generation' => $generation, 'keys' => $keys, 'icons' => $icons], $ttl);
        $this->index = null;
    }

    /**
     * The value for a channel in one of the index maps: by guide id first, then by name.
     *
     * @param  array<string, string>  $map
     */
    private function find(array $map, ?string $epgChannelId, string $name, ?string &$method = null): ?string
    {
        $method = null;
        if ($epgChannelId !== null && $epgChannelId !== '' && isset($map['id:'.$epgChannelId])) {
            $method = 'id';

            return $map['id:'.$epgChannelId];
        }
        $candidates = array_values(array_filter([$epgChannelId, $name]));
        foreach ($candidates as $candidate) {
            $key = $map[ChannelName::key($candidate)] ?? null;
            if ($key !== null) {
                $method = 'name';

                return $key;
            }
        }

        // Last pass: tolerate a small spelling/spacing difference, but only when one guide
        // channel is clearly the closest match. Never use a shortened name for programmes.
        $nameKeys = array_values(array_filter(
            array_keys($map),
            fn (string $key) => ! str_starts_with($key, 'id:')
        ));
        foreach ($candidates as $candidate) {
            $matched = ChannelName::bestMatchKey($candidate, $nameKeys);
            if ($matched !== null) {
                $method = 'approximate';

                return $map[$matched];
            }
        }

        return null;
    }

    /**
     * The cached index, or null without a guide. A missing index (first run, the scheduler not
     * running) is built after the response is sent, once at a time.
     *
     * @return array{generation: string, keys: array<string, string>, icons: array<string, string>}|null
     */
    private function index(): ?array
    {
        if (! $this->configured()) {
            return null;
        }
        if ($this->index !== null) {
            return $this->index;
        }

        $cached = Cache::get(self::INDEX_KEY);
        if (is_array($cached)) {
            return $this->index = $cached;
        }

        if (Cache::add(self::REFRESHING_KEY, true, 900)) {
            dispatch(function () {
                @set_time_limit(600);
                try {
                    // Once: another worker may have stored it meanwhile.
                    if (! is_array(Cache::get(self::INDEX_KEY))) {
                        app(self::class)->refresh();
                    }
                } finally {
                    Cache::forget(self::REFRESHING_KEY);
                }
            })->afterResponse();
        }

        return null;
    }

    private function channelKey(string $generation, string $key): string
    {
        return 'tv:epg:'.$generation.':'.md5($key);
    }

    /** Downloads one file to a temporary file (guides weigh tens of MB): never in memory. */
    private function download(string $url): string
    {
        $path = tempnam(sys_get_temp_dir(), 'epg');
        if ($path === false) {
            throw new RuntimeException('No temporary file.');
        }
        register_shutdown_function(fn () => @unlink($path));

        Http::timeout((int) config('services.tv.epg_timeout', 120))
            ->connectTimeout(10)
            ->withHeaders(['Accept-Encoding' => 'gzip'])
            ->sink($path)
            ->get($url)
            ->throw();

        return $path;
    }

    /**
     * Streams one XMLTV document: channels, then programmes in the horizon. A `.gz` file is read
     * through zlib (which reads a plain file as is).
     *
     * @return array{channels: array<string, array{names: list<string>, icon: string|null}>, programmes: array<string, list<array<string, mixed>>>}
     */
    private function parse(string $path): array
    {
        $channels = [];
        $programmes = [];
        $now = time();
        $until = $now + self::HORIZON_HOURS * 3600;

        $reader = new XMLReader;
        if (! @$reader->open('compress.zlib://'.$path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Invalid XMLTV document.');
        }

        $seen = false;
        while (@$reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->name === 'tv') {
                $seen = true;
            } elseif ($reader->name === 'channel') {
                $id = (string) $reader->getAttribute('id');
                $node = @simplexml_load_string($reader->readOuterXml());
                if ($id === '' || $node === false) {
                    continue;
                }
                $names = [];
                foreach ($node->{'display-name'} as $displayName) {
                    $names[] = (string) $displayName;
                }
                $icon = (string) ($node->icon['src'] ?? '');
                $channels[$id] = ['names' => $names, 'icon' => preg_match('#^https?://#i', $icon) ? $icon : null];
            } elseif ($reader->name === 'programme') {
                $start = self::time((string) $reader->getAttribute('start'));
                $end = self::time((string) $reader->getAttribute('stop'));
                $channel = (string) $reader->getAttribute('channel');
                if ($start === null || $end === null || $end <= $now || $start > $until || $channel === '') {
                    continue;
                }
                $node = @simplexml_load_string($reader->readOuterXml());
                if ($node === false) {
                    continue;
                }
                $image = (string) ($node->icon['src'] ?? '');
                $description = self::text($node->desc);
                $programmes[$channel][] = [
                    'title' => self::text($node->title) ?? '',
                    'subtitle' => self::text($node->{'sub-title'}),
                    'description' => $description === null ? null : mb_strimwidth($description, 0, self::MAX_DESCRIPTION, '…'),
                    'category' => self::text($node->category),
                    // Only https images: the app would refuse the others.
                    'image' => str_starts_with($image, 'https://') ? $image : null,
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }
        $reader->close();

        if (! $seen) {
            throw new RuntimeException('Invalid XMLTV document.');
        }

        // A channel that only appears in programmes still gets a guide entry.
        foreach (array_keys($programmes) as $id) {
            $channels[$id] ??= ['names' => [], 'icon' => null];
        }

        return ['channels' => $channels, 'programmes' => $programmes];
    }

    /** XMLTV time: "20261110203000 +0100". */
    private static function time(string $value): ?int
    {
        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('YmdHis O', $value)
            ?: \DateTimeImmutable::createFromFormat('YmdHis', substr($value, 0, 14), new \DateTimeZone('UTC'));

        return $date === false ? null : $date->getTimestamp();
    }

    private static function text(mixed $node): ?string
    {
        $value = trim((string) ($node ?? ''));

        return $value === '' ? null : $value;
    }
}
