<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

final class ReportTrace
{
    public static function raw(string $content, array $meta): void
    {
        if (! config('reports.trace_structure', false)) {
            return;
        }
        $directory = storage_path('app/private/report-traces');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $key = hash('sha256', json_encode(array_intersect_key($meta, array_flip(['chart_id', 'door', 'stage', 'attempt', 'trace_id']))));
        file_put_contents($directory.'/'.$key.'.json', json_encode([
            'meta' => array_intersect_key($meta, array_flip(['chart_id', 'door', 'stage', 'attempt', 'trace_id'])),
            'schema_version' => ReportState::SCHEMA_VERSION,
            'prompt_version' => ReportState::PROMPT_VERSION,
            'raw_response_content' => $content,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public static function record(string $source, array $state, array $meta = []): void
    {
        if (! config('reports.trace_structure', false)) {
            return;
        }
        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/report-structure.log'),
            'level' => 'info',
        ])->info('Report structure trace', [
            ...array_intersect_key($meta, array_flip(['chart_id', 'section_id', 'interpretation_id', 'trace_id', 'stage', 'attempt'])),
            'schema_version' => ReportState::SCHEMA_VERSION,
            'prompt_version' => ReportState::PROMPT_VERSION,
            'source' => $source,
            'development' => $state['development'] ?? [],
            'characteristics_count' => count($state['characteristics'] ?? []),
            'guidelines_count' => count($state['guidelines'] ?? []),
            'examples_count' => count($state['examples'] ?? []),
        ]);
    }
}
