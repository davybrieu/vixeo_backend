<?php

namespace App\Console\Commands;

use App\Services\Imdb\ImdbDatasets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Imports IMDb's ratings and episodes from its datasets (docs/notes.md) when IMDb published new
 * ones: run every hour, it compares the Last-Modified of the files (HEAD requests) with the last
 * import's. `--force` imports whatever the version.
 */
class ImportImdbRatings extends Command
{
    protected $signature = 'imdb:import {--force : Import even if the datasets did not change}';

    protected $description = 'Import IMDb ratings (title.ratings) and rated episodes (title.episode) when IMDb published new datasets';

    public function handle(ImdbDatasets $datasets): int
    {
        $started = microtime(true);
        try {
            $result = $datasets->importIfChanged((bool) $this->option('force'));
        } catch (Throwable $e) {
            report($e);
            $this->error('IMDb import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $result['imported']) {
            $this->info("IMDb datasets unchanged ({$result['version']}): nothing to import.");

            return self::SUCCESS;
        }

        $message = sprintf(
            '%d ratings and %d episodes imported in %ds (%s).',
            $result['ratings'] ?? 0,
            $result['episodes'] ?? 0,
            (int) round(microtime(true) - $started),
            $result['version'],
        );
        // In the application log too (Forge's « Logs »): an import runs in the background.
        Log::info("IMDb import: {$message}");
        $this->info($message);

        return self::SUCCESS;
    }
}
