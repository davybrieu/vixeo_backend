<?php

namespace App\Console\Commands;

use App\Services\Tmdb\CatalogWarmer;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Prepares the home in the cache (CatalogWarmer), every 30 minutes: opening the app never waits
 * for TMDb. `--details` asks TMDb again for the details of its titles, whenever they were last.
 */
class WarmCatalog extends Command
{
    protected $signature = 'catalog:warm {--details : Ask TMDb again for the details of the home titles}';

    protected $description = 'Fill the cache of the home (TMDb lists, rows and title details) before anyone asks for it';

    public function handle(CatalogWarmer $warmer): int
    {
        if (! TmdbClient::configured()) {
            $this->warn('TMDb n’est pas configuré (TMDB_READ_ACCESS_TOKEN ou TMDB_API_KEY) : rien à préparer.');

            return self::FAILURE;
        }

        $started = microtime(true);
        try {
            $result = $warmer->warm((bool) $this->option('details'));
        } catch (Throwable $e) {
            report($e);
            $this->error('Accueil non préparé : '.($e->getMessage() ?: class_basename($e)).'.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Accueil prêt en %s s : %d rangées, %d titres avec leur résumé (%s).',
            number_format(microtime(true) - $started, 1, ',', ' '),
            $result['rows'],
            $result['titles'],
            $result['details_refreshed'] ? 'fiches redemandées à TMDb' : 'fiches manquantes seulement',
        ));

        return self::SUCCESS;
    }
}
