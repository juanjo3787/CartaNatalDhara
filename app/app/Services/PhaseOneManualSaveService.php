<?php

namespace App\Services;

use App\Models\Chart;
use App\Models\ReportJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class PhaseOneManualSaveService
{
    public function save(Chart $chart, array $shared, array $doors): int
    {
        return DB::transaction(function () use ($chart, $shared, $doors): int {
            $chart = Chart::whereKey($chart->id)->lockForUpdate()->firstOrFail();
            abort_if(ReportJob::where('active_chart_id', $chart->id)->exists(), 409, 'Espera a que termine la generación antes de guardar cambios en este informe.');
            abort_if($chart->report_is_stale, 409, 'Regenera el Informe I con los datos actuales antes de editarlo.');
            $reports = app(PhaseOneReportService::class);
            $reports->build($chart, storedOnly: true);
            abort_if(array_keys($shared) !== ['concepts'], 422, 'Los opuestos complementarios debe conservar apartados, características, palabras clave y estados, sin introducción ni síntesis.');
            abort_if(array_diff(InteriorMapMaster::DOORS, array_keys($doors)) || count($doors) !== 4, 422, 'Se requieren los cuatro bloques de factores natales.');
            $master = app(InteriorMapMaster::class);
            $facts = $master->facts($chart);
            $chapters = [];
            foreach ([...$doors, 'opposites' => $shared] as $chapter => $blocks) {
                $field = $chapter === 'opposites' ? 'shared' : 'doors';
                try {
                    if (! is_array($blocks) || array_keys($blocks) !== ['concepts'] || ! is_string($blocks['concepts'])) {
                        throw new RuntimeException('Cada bloque debe conservar sus apartados, características, palabras clave y estados de expresión.');
                    }
                    $chapters[$chapter] = $reports->parseChapter($blocks['concepts']);
                    $master->validateFacts($chapters[$chapter], $facts, $chapter === 'opposites' ? null : $chapter);
                } catch (RuntimeException $exception) {
                    throw ValidationException::withMessages([$field => $exception->getMessage()]);
                }
            }
            try {
                $master->assertNoDuplicateParagraphs($chapters);
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['doors' => $exception->getMessage()]);
            }
            foreach ($chapters as $chapter => $content) {
                $html = $chapter === 'opposites' ? $shared['concepts'] : $doors[$chapter]['concepts'];
                $reports->storeChapter($chart, $chapter === 'opposites' ? null : $chapter, $content, assisted: false, editedHtml: $html);
            }

            return count($chapters);
        });
    }
}
