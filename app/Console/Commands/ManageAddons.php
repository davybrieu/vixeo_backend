<?php

namespace App\Console\Commands;

use App\Models\Addon;
use App\Services\Addons\Addons;
use App\Services\Addons\InvalidAddon;
use Illuminate\Console\Command;

/**
 * Plug-ins installed on the server for every account (docs/plugins.md). An account cannot
 * change nor remove them; each account still installs its own from the app.
 *
 *     php artisan addons:server list
 *     php artisan addons:server install https://addon.example.com/manifest.json
 *     php artisan addons:server remove 12
 */
class ManageAddons extends Command
{
    protected $signature = 'addons:server
        {action : list, install ou remove}
        {target? : adresse du manifeste (install) ou numéro du plug-in (remove)}';

    protected $description = 'List, install or remove the plug-ins of every account';

    public function handle(Addons $addons): int
    {
        return match ($this->argument('action')) {
            'list' => $this->list(),
            'install' => $this->install($addons, (string) $this->argument('target')),
            'remove' => $this->remove((string) $this->argument('target')),
            default => $this->fail('Action inconnue : list, install ou remove.'),
        };
    }

    private function list(): int
    {
        $rows = Addon::query()->whereNull('user_id')->orderBy('position')->get()->map(fn (Addon $a) => [
            $a->id, $a->parsedManifest()->name, $a->parsedManifest()->version, $a->host(), $a->enabled ? 'oui' : 'non',
        ]);
        $this->table(['N°', 'Nom', 'Version', 'Hôte', 'Activé'], $rows);
        $this->line(Addon::query()->whereNotNull('user_id')->count().' plug-in(s) installé(s) par les comptes.');

        return self::SUCCESS;
    }

    private function install(Addons $addons, string $url): int
    {
        if ($url === '') {
            return $this->fail('Donnez l’adresse du manifeste.');
        }
        try {
            $addon = $addons->install(null, $url);
        } catch (InvalidAddon $e) {
            return $this->fail($e->getMessage());
        }
        $this->info("« {$addon->parsedManifest()->name} » installé pour tous les comptes (n° {$addon->id}).");

        return self::SUCCESS;
    }

    private function remove(string $id): int
    {
        $addon = ctype_digit($id) ? Addon::query()->whereNull('user_id')->find((int) $id) : null;
        if ($addon === null) {
            return $this->fail('Aucun plug-in de ce numéro installé pour tous les comptes.');
        }
        $addon->delete();
        $this->info('Plug-in retiré.');

        return self::SUCCESS;
    }
}
