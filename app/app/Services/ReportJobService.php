<?php

namespace App\Services;

use App\Jobs\ProcessReportStep;
use App\Models\Chart;
use App\Models\ReportJob;
use App\Services\Doors\AbstractDoorPipeline;
use Illuminate\Support\Facades\DB;

class ReportJobService
{
    public function start(Chart $chart, int $userId, array $doors, ?string $wheelImage = null): ReportJob
    {
        return DB::transaction(function () use ($chart, $userId, $doors, $wheelImage): ReportJob {
            Chart::whereKey($chart->id)->lockForUpdate()->firstOrFail();
            $active = ReportJob::where('active_chart_id', $chart->id)->first();
            if ($active) {
                abort_unless($active->user_id === $userId, 409, 'Esta carta ya tiene una generación en curso.');

                return $active;
            }
            if ($wheelImage) {
                $chart->update(['natal_wheel_image' => $wheelImage]);
            }
            $job = ReportJob::create(['chart_id' => $chart->id, 'active_chart_id' => $chart->id, 'user_id' => $userId, 'doors' => $doors, 'status' => 'queued']);
            ProcessReportStep::dispatch($job->id, 0)->onConnection('reports')->onQueue('reports');

            return $job;
        });
    }

    public function retry(ReportJob $job): ReportJob
    {
        return DB::transaction(function () use ($job): ReportJob {
            Chart::whereKey($job->chart_id)->lockForUpdate()->firstOrFail();
            $job->refresh();
            abort_unless($job->status === 'failed', 409, 'El trabajo no está fallido.');
            abort_if(ReportJob::where('active_chart_id', $job->chart_id)->exists(), 409, 'Ya hay una generación activa.');
            abort_if(ReportJob::where('chart_id', $job->chart_id)->where('id', '>', $job->id)->exists(), 409, 'Existe un trabajo posterior. Usa el trabajo más reciente para esta carta.');
            $job->update(['status' => 'queued', 'active_chart_id' => $job->chart_id, 'error_code' => null, 'error_message' => null, 'completed_at' => null]);
            ProcessReportStep::dispatch($job->id, $job->cursor)->onConnection('reports')->onQueue('reports');

            return $job;
        });
    }

    public function steps(ReportJob $job): array
    {
        $steps = [['status' => 'preparing']];
        $names = ['sol' => 'sun', 'luna' => 'moon', 'ascendente' => 'ascendant', 'descendente' => 'descendant'];
        foreach ($job->doors as $door) {
            foreach (AbstractDoorPipeline::STAGES as $stage) {
                $steps[] = ['status' => 'generating_'.$names[$door], 'door' => $door, 'stage' => $stage];
            }
        }
        foreach (['generating_integration', 'validating', 'building_document', 'generating_pdf'] as $status) {
            $steps[] = compact('status');
        }

        return $steps;
    }

    public function process(ReportJob $job): void
    {
        $steps = $this->steps($job);
        $step = $steps[$job->cursor];
        $job->update(['status' => $step['status'], 'current_section' => isset($step['door']) ? $step['door'].'.'.$step['stage'] : $step['status'], 'started_at' => $job->started_at ?? now()]);
        $chart = $job->chart;
        if (isset($step['door'])) {
            app(PhaseOneAiGenerationService::class)->generateDoorStage($chart, $step['door'], $step['stage'], 'job-'.$job->id, $job);
        } elseif (in_array($step['status'], ['generating_integration', 'validating', 'building_document'], true)) {
            $reportService = app(PhaseOneReportService::class);
            $report = $reportService->build($chart);
            if ($step['status'] === 'building_document') {
                $reportService->persistGeneratedContent($chart, $report);
            }
        } elseif ($step['status'] === 'generating_pdf') {
            app(ReportPdfService::class)->store($chart, $job->id);
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
