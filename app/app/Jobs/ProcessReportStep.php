<?php

namespace App\Jobs;

use App\Exceptions\AiGenerationException;
use App\Models\ReportJob;
use App\Services\ReportJobService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessReportStep implements ShouldQueue
{
    use Queueable;

    public int $timeout = 360;

    public int $tries = 4;

    public bool $failOnTimeout = true;

    public function __construct(public int $reportJobId, public int $cursor) {}

    public function backoff(): array
    {
        return [60, 180, 540];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('report-'.$this->reportJobId))->releaseAfter(15)->expireAfter(420)];
    }

    /**
     * Execute the job.
     */
    public function handle(ReportJobService $service): void
    {
        $job = ReportJob::find($this->reportJobId);
        if (! $job || $job->cursor !== $this->cursor || in_array($job->status, ['completed', 'failed'], true)) {
            return;
        }
        $service->process($job);
    }

    public function failed(?Throwable $exception): void
    {
        ReportJob::whereKey($this->reportJobId)->where('cursor', $this->cursor)->whereNotIn('status', ['completed', 'failed'])->update([
            'status' => 'failed', 'active_chart_id' => null, 'completed_at' => now(),
            'error_code' => $exception instanceof AiGenerationException ? $exception->errorCode : 'REPORT_STEP_FAILED',
            'error_message' => 'No se pudo completar esta etapa. Puedes reintentar sin repetir las etapas guardadas.',
        ]);
    }
}
