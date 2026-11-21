<?php

namespace App\Services\Imdb;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Imports IMDb's ratings from its datasets (https://datasets.imdbws.com, docs/notes.md) when IMDb
 * published new ones (`php artisan imdb:import`, every hour: a HEAD request per file compares their
 * Last-Modified with the last import's, table imdb_imports): title.ratings.tsv.gz (rating and number of votes of each
 * title: movies, TV shows, episodes…) into imdb_ratings, and title.episode.tsv.gz (show, season
 * and number of each episode) into imdb_episodes, for the episodes that have a rating.
 *
 * Files are streamed to a temporary file, read line by line and saved by chunks (upserts):
 * memory stays low whatever their size. Rows IMDb no longer has are removed at the end of a
 * complete import only.
 */
class ImdbDatasets
{
    /** Rows saved per query. */
    public const CHUNK = 2000;

    /** The datasets read, in this order. */
    public const FILES = ['title.ratings.tsv.gz', 'title.episode.tsv.gz'];

    /** Imports remembered (one a day, when IMDb publishes): older ones are deleted. */
    private const KEEP_IMPORTS_DAYS = 30;

    /**
     * Imports the datasets if IMDb published new ones since the last import (or [force]).
     *
     * @return array{version: string, imported: bool, ratings?: int, episodes?: int}
     */
    public function importIfChanged(bool $force = false): array
    {
        $version = $this->remoteVersion();
        if (! $force && $version === $this->importedVersion()) {
            return ['version' => $version, 'imported' => false];
        }

        $counts = $this->import();
        DB::table('imdb_imports')->insert([...$counts, 'version' => $version, 'created_at' => now()]);
        DB::table('imdb_imports')->where('created_at', '<', now()->subDays(self::KEEP_IMPORTS_DAYS))->delete();

        return ['version' => $version, 'imported' => true, ...$counts];
    }

    /**
     * Version of the datasets IMDb publishes now: the Last-Modified (else the ETag) of each file,
     * from HEAD requests (nothing downloaded). A server giving neither: the day, so that the
     * datasets are imported once a day.
     */
    public function remoteVersion(): string
    {
        $parts = [];
        foreach (self::FILES as $file) {
            $response = Http::connectTimeout(15)->timeout(30)->retry(2, 2000)->head($this->url($file))->throw();
            $stamp = $response->header('Last-Modified') ?: $response->header('ETag');
            $parts[] = $file.'='.($stamp !== '' ? $stamp : 'day '.now()->toDateString());
        }

        return implode('; ', $parts);
    }

    /** Version of the last complete import, null before the first one. */
    public function importedVersion(): ?string
    {
        return DB::table('imdb_imports')->orderByDesc('id')->value('version');
    }

    /**
     * Downloads and imports both datasets, whatever their version.
     *
     * @return array{ratings: int, episodes: int} rows imported
     */
    public function import(): array
    {
        $importId = time();

        $ratings = $this->importRatings($importId);
        if ($ratings === 0) {
            throw new RuntimeException('IMDb gave no rating: nothing changed.');
        }
        $episodes = $this->importEpisodes($importId);

        DB::table('imdb_ratings')->where('import_id', '<', $importId)->delete();
        DB::table('imdb_episodes')->where('import_id', '<', $importId)->delete();

        return ['ratings' => $ratings, 'episodes' => $episodes];
    }

    private function importRatings(int $importId): int
    {
        $count = 0;
        $chunk = [];
        foreach ($this->rows(self::FILES[0]) as $row) {
            // tconst, averageRating, numVotes.
            if (count($row) < 3 || ! self::isTitleId($row[0]) || ! is_numeric($row[1]) || ! ctype_digit($row[2])) {
                continue;
            }
            $chunk[] = ['tconst' => $row[0], 'rating' => round((float) $row[1], 1), 'votes' => (int) $row[2], 'import_id' => $importId];
            if (count($chunk) >= self::CHUNK) {
                $count += $this->save('imdb_ratings', $chunk, ['rating', 'votes', 'import_id']);
                $chunk = [];
            }
        }

        return $count + $this->save('imdb_ratings', $chunk, ['rating', 'votes', 'import_id']);
    }

    private function importEpisodes(int $importId): int
    {
        $count = 0;
        $chunk = [];
        foreach ($this->rows(self::FILES[1]) as $row) {
            // tconst, parentTconst, seasonNumber, episodeNumber (\N when unknown).
            if (count($row) < 4 || ! self::isTitleId($row[0]) || ! self::isTitleId($row[1])) {
                continue;
            }
            $chunk[$row[0]] = [
                'tconst' => $row[0],
                'parent_tconst' => $row[1],
                'season' => ctype_digit($row[2]) ? min((int) $row[2], 65535) : null,
                'episode' => ctype_digit($row[3]) ? (int) $row[3] : null,
                'import_id' => $importId,
            ];
            if (count($chunk) >= self::CHUNK) {
                $count += $this->saveRatedEpisodes($chunk);
                $chunk = [];
            }
        }

        return $count + $this->saveRatedEpisodes($chunk);
    }

    /** Keeps the episodes that have a rating (most of IMDb's episodes have none). */
    private function saveRatedEpisodes(array $episodes): int
    {
        if ($episodes === []) {
            return 0;
        }
        $rated = DB::table('imdb_ratings')->whereIn('tconst', array_keys($episodes))->pluck('tconst')->all();

        return $this->save(
            'imdb_episodes',
            array_values(array_intersect_key($episodes, array_flip($rated))),
            ['parent_tconst', 'season', 'episode', 'import_id'],
        );
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function save(string $table, array $rows, array $update): int
    {
        if ($rows !== []) {
            DB::table($table)->upsert($rows, ['tconst'], $update);
        }

        return count($rows);
    }

    /**
     * Rows of a dataset (tab-separated, gzip), its header skipped.
     *
     * @return \Generator<int, list<string>>
     */
    private function rows(string $file): \Generator
    {
        $path = tempnam(sys_get_temp_dir(), 'imdb');
        try {
            Http::connectTimeout(30)->timeout(900)->retry(2, 10000)->sink($path)->get($this->url($file))->throw();

            $handle = gzopen($path, 'rb');
            if ($handle === false) {
                throw new RuntimeException("IMDb dataset {$file} cannot be read.");
            }
            try {
                $header = true;
                while (($line = gzgets($handle)) !== false) {
                    if ($header) {
                        $header = false;

                        continue;
                    }
                    $line = rtrim($line, "\r\n");
                    if ($line !== '') {
                        yield explode("\t", $line);
                    }
                }
            } finally {
                gzclose($handle);
            }
        } finally {
            @unlink($path);
        }
    }

    private function url(string $file): string
    {
        return rtrim((string) config('services.imdb.datasets_url'), '/').'/'.$file;
    }

    public static function isTitleId(mixed $value): bool
    {
        return is_string($value) && preg_match('/^tt\d{1,12}$/', $value) === 1;
    }
}
