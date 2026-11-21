<?php

namespace App\Console\Commands;

use App\Services\Imdb\ImdbDatasets;
use Illuminate\Console\Command;
use Throwable;

/** Imports IMDb's ratings and episodes from its datasets (docs/notes.md), every day. */
class ImportImdbRatings extends Command
{
    protected $signature = 'imdb:import';

    protected $description = 'Import IMDb ratings (title.ratings) and rated episodes (title.episode) from IMDb datasets';

    public function handle(ImdbDatasets $datasets): int
    {
        $started = microtime(true);
        try {
            $counts = $datasets->import();
        } catch (Throwable $e) {
            report($e);
            $this->error('IMDb import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d ratings and %d episodes imported in %ds.',
            $counts['ratings'],
            $counts['episodes'],
            (int) round(microtime(true) - $started),
        ));

        return self::SUCCESS;
    }
}
