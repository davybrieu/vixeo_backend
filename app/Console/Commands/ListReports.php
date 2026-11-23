<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Services\Social\Reports;
use Illuminate\Console\Command;

/** The reports waiting for the moderation (docs/moderation.md). */
class ListReports extends Command
{
    protected $signature = 'vixeo:reports {--all : Also the handled ones (the latest 50)}';

    protected $description = 'Liste les signalements à examiner';

    public function handle(Reports $reports): int
    {
        $rows = Report::query()->with(['reporter', 'author'])
            ->when(! $this->option('all'), fn ($q) => $q->where('status', Report::OPEN))
            ->latest('id')->limit(50)->get();
        if ($rows->isEmpty()) {
            $this->info('Aucun signalement à examiner.');

            return self::SUCCESS;
        }
        $this->table(['N°', 'Date', 'Type', 'Raison', 'Auteur', 'Par', 'Contenu', 'Statut'], $rows->map(fn (Report $r) => [
            $r->id,
            $r->created_at?->format('d/m H:i'),
            $r->subject_type,
            Reports::REASONS[$r->reason] ?? $r->reason,
            $r->author === null ? '—' : '@'.$r->author->username,
            $r->reporter === null ? '—' : '@'.$r->reporter->username,
            $reports->excerpt($r).($r->details ? " [{$r->details}]" : ''),
            $r->status,
        ])->all());
        $this->line('Décider : php artisan vixeo:moderate <n°> hide|dismiss|restore --reason="…"');

        return self::SUCCESS;
    }
}
