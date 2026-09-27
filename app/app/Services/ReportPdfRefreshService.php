<?php

namespace App\Services;

use App\Models\Chart;
use App\Models\ReportGeneration;
use App\Models\ReportJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ReportPdfRefreshService
{
    /** @return array{path: string, backup: ?string} */
    public function refresh(Chart $chart, bool $dryRun = false): array
    {
        return DB::transaction(function () use ($chart, $dryRun): array {
            $chart = Chart::whereKey($chart->id)->lockForUpdate()->firstOrFail();
            if (ReportJob::where('active_chart_id', $chart->id)->exists()) {
                throw new RuntimeException('Hay una generación activa. Espera a que termine.');
            }
            $disk = Storage::disk('local');
            $previous = $chart->phase_one_pdf ?: $chart->reportGenerations()
                ->where('ai_assisted', false)->latest('id')->value('filename');
            if (! $previous || ! $disk->exists($previous)) {
                throw new RuntimeException('Falta el PDF anterior para conservar una copia recuperable.');
            }
            $this->validateSavedData($chart);
            $report = app(PhaseOneReportService::class)->build($chart, storedOnly: true);
            if ($dryRun) {
                return ['path' => $previous, 'backup' => null];
            }

            $pdf = app(ReportPdfService::class)->render($chart, $report, $chart->natal_wheel_image);
            $id = (string) Str::uuid();
            $backup = 'pdfs/'.$chart->id.'/backups/'.$id.'.pdf';
            $path = 'pdfs/'.$chart->id.'/refresh-'.$id.'-v'.PdfPageGeometry::VERSION.'.pdf';
            if (! $disk->copy($previous, $backup)) {
                throw new RuntimeException('No se pudo conservar la copia del PDF anterior.');
            }
            try {
                if (! $disk->put($path, $pdf)) {
                    throw new RuntimeException('No se pudo guardar el nuevo PDF.');
                }
                $chart->update(['phase_one_pdf' => $path, 'phase_one_pdf_generated_at' => now()]);
                ReportGeneration::create(['chart_id' => $chart->id, 'report_type' => 'fase-1', 'filename' => $path, 'size_bytes' => strlen($pdf), 'checksum' => hash('sha256', $pdf), 'ai_assisted' => false]);
            } catch (\Throwable $exception) {
                $disk->delete($path);
                throw $exception;
            }

            return ['path' => $path, 'backup' => $backup];
        });
    }

    private function validateSavedData(Chart $chart): void
    {
        $chart->loadMissing('person', 'birthData.place');
        if (! $chart->person || ! $chart->birthData?->place || ! $chart->birthData->local_date || ! $chart->birthData->local_time) {
            throw new RuntimeException('Faltan datos guardados de la persona, nacimiento o lugar.');
        }
        $snapshot = $chart->snapshot ?? [];
        foreach (['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto', 'ascendant', 'descendant'] as $point) {
            foreach (['longitude', 'sign', 'degrees', 'minutes', 'seconds'] as $field) {
                if (! isset($snapshot[$point][$field])) {
                    throw new RuntimeException('Falta el dato astrológico guardado: '.$point.'.'.$field);
                }
            }
        }
        foreach (range(1, 12) as $house) {
            foreach (['longitude', 'sign', 'degrees', 'minutes', 'seconds'] as $field) {
                if (! isset($snapshot['houses'][$house][$field])) {
                    throw new RuntimeException('Falta el dato de la casa guardada: '.$house.'.'.$field);
                }
            }
        }
        $wheel = (string) $chart->natal_wheel_image;
        if (! preg_match('#^data:image/(?:jpeg|png);base64,(.+)$#s', $wheel, $match)) {
            throw new RuntimeException('Falta una imagen guardada de la rueda en formato PNG o JPEG.');
        }
        $bytes = base64_decode($match[1], true);
        if ($bytes === false || @getimagesizefromstring($bytes) === false) {
            throw new RuntimeException('La imagen guardada de la rueda no es válida.');
        }
    }
}
