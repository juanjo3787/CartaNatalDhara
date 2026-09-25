<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportMetrics
{
    public function begin(?int $jobId, string $kind, string $section, ?string $model = null): ?int
    {
        if ($jobId === null) {
            return null;
        }
        try {
            $attempt = DB::table('report_metrics')->where('report_job_id', $jobId)
                ->where('kind', $kind)->where('section_id', $section)->count() + 1;

            return DB::table('report_metrics')->insertGetId([
                'report_job_id' => $jobId, 'kind' => $kind, 'section_id' => $section,
                'cohort' => config('reports.metrics_cohort'), 'attempt' => $attempt,
                'model' => $model, 'started_at' => now()->format('Y-m-d H:i:s.v'), 'outcome' => 'running',
            ]);
        } catch (\Throwable) {
            Log::warning('Report metrics unavailable', ['job_id' => $jobId]);

            return null;
        }
    }

    public function finish(?int $id, int $started, ?\Throwable $error = null, array $usage = []): void
    {
        if ($id === null) {
            return;
        }
        try {
            DB::table('report_metrics')->where('id', $id)->update([
                'finished_at' => now()->format('Y-m-d H:i:s.v'),
                'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'outcome' => $error ? 'error' : 'success',
                'error_code' => $error instanceof \App\Exceptions\AiGenerationException ? $error->errorCode : ($error ? 'PROCESSING_ERROR' : null),
                'input_tokens' => $usage['input_tokens'] ?? null,
                'output_tokens' => $usage['output_tokens'] ?? null,
            ]);
        } catch (\Throwable) {
            Log::warning('Report metric could not be completed', ['metric_id' => $id]);
        }
    }
}
