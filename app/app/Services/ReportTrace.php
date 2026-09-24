<?php

namespace App\Services;

final class ReportTrace
{
    public static function record(string $source, array $state, array $meta = []): void
    {
        if (! config('reports.trace_structure', false)) {
            return;
        }
        \Illuminate\Support\Facades\Log::info('Report structure trace', [
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
