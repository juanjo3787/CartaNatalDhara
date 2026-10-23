<?php

namespace App\Services;

use App\Jobs\ProcessReportStep;
use App\Models\Chart;
use App\Models\ReportJob;
use Illuminate\Support\Facades\DB;

class ReportJobService
{
    public function start(Chart $chart, int $userId, array $doors, ?string $wheelImage = null): ReportJob
    {
        return DB::transaction(function () use ($chart, $userId, $doors, $wheelImage): ReportJob {
            $chart = Chart::whereKey($chart->id)->lockForUpdate()->firstOrFail();
            $active = ReportJob::where('active_chart_id', $chart->id)->first();
            if ($active) {
                abort_unless($active->user_id === $userId, 409, 'Esta carta ya tiene una generación en curso.');

                return $active;
            }
            if ($wheelImage) {
                $chart->update(['natal_wheel_image' => $wheelImage]);
            }
            if ($doors !== []) {
                $doors = InteriorMapMaster::DOORS;
            }
            abort_if($chart->report_is_stale && $doors === [], 409, 'Los datos han cambiado. Regenera el Informe I completo.');
            app(InteriorMapMaster::class)->facts($chart);
            if ($doors !== []) {
                $chart->update(['report_is_stale' => true, 'phase_one_pdf' => null, 'phase_one_pdf_generated_at' => null]);
                $chart->interpretations()->where('phase', 'fase-1')->delete();
            } else {
                abort_unless(app(PhaseOneReportService::class)->build($chart)['complete'], 409, 'Genera el Informe I completo antes de guardar un PDF.');
            }
            $job = ReportJob::create(['chart_id' => $chart->id, 'active_chart_id' => $chart->id, 'user_id' => $userId, 'doors' => $doors, 'status' => 'queued', 'drafts' => ['_master_version' => InteriorMapMaster::VERSION, '_chart_fingerprint' => app(InteriorMapMaster::class)->fingerprint($chart)]]);
            ProcessReportStep::dispatch($job->id, 0)->onConnection('reports')->onQueue('reports');

            return $job;
        });
    }

    public function retry(ReportJob $job): ReportJob
    {
        return DB::transaction(function () use ($job): ReportJob {
            Chart::whereKey($job->chart_id)->lockForUpdate()->firstOrFail();
            $job->refresh();
            abort_unless(($job->drafts['_master_version'] ?? null) === InteriorMapMaster::VERSION, 409, 'Trabajo del sistema anterior. Inicia un Informe I nuevo.');
            abort_unless(($job->drafts['_chart_fingerprint'] ?? null) === app(InteriorMapMaster::class)->fingerprint($job->chart->fresh()), 409, 'La carta cambió desde este trabajo. Genera un Informe I nuevo.');
            abort_unless($job->status === 'failed', 409, 'El trabajo no está fallido.');
            abort_if(ReportJob::where('active_chart_id', $job->chart_id)->exists(), 409, 'Ya hay una generación activa.');
            abort_if(ReportJob::where('chart_id', $job->chart_id)->where('id', '>', $job->id)->exists(), 409, 'Existe un trabajo posterior. Usa el trabajo más reciente para esta carta.');
            $job->update(['status' => 'queued', 'active_chart_id' => $job->chart_id, 'dismissed_at' => null, 'error_code' => null, 'error_message' => null, 'completed_at' => null]);
            ProcessReportStep::dispatch($job->id, $job->cursor)->onConnection('reports')->onQueue('reports');

            return $job;
        });
    }

    public function steps(ReportJob $job): array
    {
        $steps = [['status' => 'preparing']];
        $names = ['sol' => 'sun', 'luna' => 'moon', 'ascendente' => 'ascendant', 'descendente' => 'descendant'];
        foreach ($job->doors as $door) {
            $steps[] = ['status' => 'generating_'.$names[$door], 'door' => $door, 'stage' => 'interior_map'];
        }
        if ($job->doors !== []) {
            $steps[] = ['status' => 'generating_opposites'];
            $steps[] = ['status' => 'auditing'];
        }
        foreach (['validating', 'building_document', 'generating_pdf'] as $status) {
            $steps[] = compact('status');
        }

        return $steps;
    }

    public function process(ReportJob $job): void
    {
        if (($job->drafts['_master_version'] ?? null) !== InteriorMapMaster::VERSION) {
            throw new \RuntimeException('Trabajo del sistema anterior. Inicia un Informe I nuevo.');
        }
        if (($job->drafts['_chart_fingerprint'] ?? null) !== app(InteriorMapMaster::class)->fingerprint($job->chart->fresh())) {
            throw new \RuntimeException('La carta cambió desde este trabajo. Genera un Informe I nuevo.');
        }
        $step = $this->steps($job)[$job->cursor];
        $section = isset($step['door']) ? $step['door'].'.'.$step['stage'] : $step['status'];
        $metrics = app(ReportMetrics::class);
        $id = $metrics->begin($job->id, 'step', $section);
        $started = hrtime(true);
        $error = null;
        try {
            $this->processStep($job);
        } catch (\Throwable $exception) {
            $error = $exception;
            throw $exception;
        } finally {
            $metrics->finish($id, $started, $error);
        }
    }

    private function processStep(ReportJob $job): void
    {
        $steps = $this->steps($job);
        $step = $steps[$job->cursor];
        $job->update(['status' => $step['status'], 'current_section' => isset($step['door']) ? $step['door'].'.'.$step['stage'] : $step['status'], 'started_at' => $job->started_at ?? now()]);
        $chart = $job->chart;
        if (isset($step['door'])) {
            app(PhaseOneAiGenerationService::class)->generateChapter($chart, $step['door'], $job);
        } elseif ($step['status'] === 'generating_opposites') {
            app(PhaseOneAiGenerationService::class)->generateChapter($chart, 'opposites', $job);
        } elseif ($step['status'] === 'auditing') {
            app(PhaseOneAiGenerationService::class)->auditReport($chart, $job);
        } elseif (in_array($step['status'], ['validating', 'building_document'], true)) {
            $reportService = app(PhaseOneReportService::class);
            if ($job->doors !== []) {
                app(PhaseOneAiGenerationService::class)->validateReport($job);
            }
            $report = $reportService->build($chart, storedOnly: true);
            if ($step['status'] === 'building_document') {
                $reportService->persistGeneratedContent($chart, $report);
            }
        } elseif ($step['status'] === 'generating_pdf') {
            $allDoors = ['sol', 'luna', 'ascendente', 'descendente'];
            $completeReport = count($job->doors) === count($allDoors)
                && array_diff($allDoors, $job->doors) === [];
            app(ReportPdfService::class)->store($chart, $job->id, $completeReport);
        }
        DB::transaction(function () use ($job, $steps): void {
            $next = $job->cursor + 1;
            $complete = $next === count($steps);
            $job->update(['cursor' => $next, 'progress' => (int) floor(100 * $next / count($steps)), 'status' => $complete ? 'completed' : $job->status, 'active_chart_id' => $complete ? null : $job->chart_id, 'completed_at' => $complete ? now() : null, 'error_code' => null, 'error_message' => null]);
            if (! $complete) {
                ProcessReportStep::dispatch($job->id, $next)->onConnection('reports')->onQueue('reports');
            }
        });
    }
}
