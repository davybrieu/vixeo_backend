<?php

namespace App\Console\Commands;

use App\Enums\AppPlatform;
use App\Models\AppRelease;
use Illuminate\Console\Command;

/** Lists the published versions of the apps, newest first. */
class ListAppReleases extends Command
{
    protected $signature = 'vixeo:releases {platform? : android ou ios}';

    protected $description = 'Liste les versions publiées de l’appli Android et iOS';

    public function handle(): int
    {
        $platform = $this->argument('platform') === null ? null : AppPlatform::tryFrom((string) $this->argument('platform'));
        if ($this->argument('platform') !== null && $platform === null) {
            $this->error('Plateforme inconnue : utilisez android ou ios.');

            return self::INVALID;
        }

        $releases = AppRelease::query()
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->orderBy('platform')
            ->orderByDesc('build')
            ->get();

        $this->table(
            ['Plateforme', 'Version', 'Build', 'Obligatoire', 'Publiée le', 'Nouveautés'],
            $releases->map(fn (AppRelease $r) => [
                $r->platform->value,
                $r->version,
                $r->build,
                $r->required ? 'oui' : 'non',
                $r->published_at->format('d/m/Y H:i').($r->published_at->isFuture() ? ' (à venir)' : ''),
                mb_strimwidth((string) $r->notes, 0, 60, '…'),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
