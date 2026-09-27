<?php

namespace App\Console\Commands;

use App\Models\Chart;
use App\Services\ReportPdfRefreshService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reports:refresh-pdfs {chart? : ID de una carta} {--all : Todos los informes existentes} {--dry-run : Comprobar sin escribir PDF ni datos}')]
#[Description('Actualiza PDF con contenido guardado, sin IA, conservando copia del anterior')]
class RefreshReportPdfs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReportPdfRefreshService $service): int
    {
        $id = $this->argument('chart');
        if (($id !== null) === (bool) $this->option('all') || ($id !== null && (! ctype_digit((string) $id) || (int) $id < 1))) {
            $this->error('Indica un ID de carta o --all, exclusivamente.');

            return self::INVALID;
        }
        $query = Chart::query();
        if ($id !== null) {
            $query->whereKey($id);
        } else {
            $query->where(fn ($query) => $query->whereNotNull('phase_one_pdf')->orWhereHas('reportGenerations', fn ($history) => $history->where('ai_assisted', false)));
        }
        $ready = 0;
        $skipped = 0;
        foreach ($query->lazyById() as $chart) {
            try {
                $result = $service->refresh($chart, (bool) $this->option('dry-run'));
                $ready++;
                $this->info('Carta '.$chart->id.($this->option('dry-run') ? ': preparada.' : ': actualizada. PDF: '.$result['path'].' | Copia anterior: '.$result['backup']));
            } catch (\Throwable $exception) {
                $skipped++;
                $this->warn('Carta '.$chart->id.': omitida. '.$exception->getMessage());
            }
        }
        $this->info(($this->option('dry-run') ? 'Preparadas: ' : 'Actualizadas: ').$ready.'. Omitidas: '.$skipped.'.');
        if ($ready + $skipped === 0) {
            $this->warn('No se encontraron informes para la selección indicada.');
        }

        return $skipped > 0 || ($id !== null && $ready === 0) ? self::FAILURE : self::SUCCESS;
    }
}
