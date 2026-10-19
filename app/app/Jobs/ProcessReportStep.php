<?php

namespace App\Jobs;

use App\Exceptions\AiGenerationException;
use App\Exceptions\ReportContentValidationException;
use App\Models\ReportJob;
use App\Services\ReportJobService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
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
        try {
            $service->process($job);
        } catch (ReportContentValidationException $exception) {
            $job->update([
                'error_code' => 'CONTENT_VALIDATION_FAILED',
                'error_message' => 'El capítulo no superó la validación: '.$exception->getMessage().' Se reintentará con instrucciones de corrección.',
            ]);
            Log::warning('Report chapter validation failed; retry will use correction feedback.', [
                'report_job_id' => $job->id,
                'current_section' => $job->current_section,
                'validation_error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $job = ReportJob::whereKey($this->reportJobId)
            ->where('cursor', $this->cursor)
            ->whereNotIn('status', ['completed', 'failed'])
            ->first(['id', 'chart_id', 'cursor', 'current_section']);
        if (! $job) {
            return;
        }

        $updated = ReportJob::whereKey($job->id)
            ->where('cursor', $this->cursor)
            ->whereNotIn('status', ['completed', 'failed'])
            ->update([
                'status' => 'failed', 'active_chart_id' => null, 'completed_at' => now(),
                'error_code' => $exception instanceof ReportContentValidationException ? 'CONTENT_VALIDATION_FAILED' : ($exception instanceof AiGenerationException ? $exception->errorCode : 'REPORT_STEP_FAILED'),
                'error_message' => $exception instanceof ReportContentValidationException
                    ? 'El capítulo no superó la validación: '.$exception->getMessage().' Puedes reintentar sin repetir las etapas guardadas.'
                    : 'No se pudo completar esta etapa. Puedes reintentar sin repetir las etapas guardadas.',
            ]);

        if ($updated) {
            Log::error('Report generation step failed permanently.', [
                'report_job_id' => $job->id,
                'chart_id' => $job->chart_id,
                'cursor' => $job->cursor,
                'current_section' => $job->current_section,
                'exception_class' => $exception ? $exception::class : null,
                'exception_message' => $exception?->getMessage(),
                'exception_code' => $exception?->getCode(),
            ]);
        }
    }
}
