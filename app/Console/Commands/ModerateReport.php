<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Services\Social\Reports;
use Illuminate\Console\Command;

/**
 * A decision of the moderation (docs/moderation.md): hide the content (suspend a profile), dismiss
 * the report, or restore what was hidden. The reason is told to its author and to who reported it.
 */
class ModerateReport extends Command
{
    protected $signature = 'vixeo:moderate {report : Its number (vixeo:reports)} {action : hide, dismiss, restore, photo or cover (removes the photo or the cover image of a reported profile)} {--reason= : Why, told to the author and to who reported it}';

    protected $description = 'Traite un signalement : masquer, rejeter ou rétablir';

    public function handle(Reports $reports): int
    {
        $report = Report::query()->find((int) $this->argument('report'));
        if ($report === null) {
            $this->error('Ce signalement n’existe pas.');

            return self::FAILURE;
        }
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('Donnez la raison de la décision : --reason="…" (elle est communiquée aux personnes concernées).');

            return self::FAILURE;
        }
        if ($this->argument('action') === 'photo') {
            if (! $reports->removePhoto($report, $reason)) {
                $this->error('Ce signalement ne vise pas un profil avec une photo.');

                return self::FAILURE;
            }
            $this->info("Signalement n° {$report->id} traité : photo retirée.");

            return self::SUCCESS;
        }
        if ($this->argument('action') === 'cover') {
            if (! $reports->removeCover($report, $reason)) {
                $this->error('Ce signalement ne vise pas un profil avec une image de couverture.');

                return self::FAILURE;
            }
            $this->info("Signalement n° {$report->id} traité : image de couverture retirée.");

            return self::SUCCESS;
        }
        match ((string) $this->argument('action')) {
            'hide' => $reports->hide($report, $reason),
            'dismiss' => $reports->dismiss($report, $reason),
            'restore' => $reports->restore($report, $reason),
            default => null,
        };
        if (! in_array($this->argument('action'), ['hide', 'dismiss', 'restore'], true)) {
            $this->error('Action inconnue : hide, dismiss, restore, photo ou cover.');

            return self::FAILURE;
        }
        $this->info("Signalement n° {$report->id} traité ({$this->argument('action')}).");

        return self::SUCCESS;
    }
}
