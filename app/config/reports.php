<?php

return [
    'compress_drafts' => (bool) env('REPORT_COMPRESS_DRAFTS', true),
    'metrics_cohort' => env('REPORT_METRICS_COHORT', 'compressed-v1'),
    'trace_structure' => (bool) env('REPORT_TRACE_STRUCTURE', false),
    // PDF rendering runs inside the report worker's per-stage execution budget.
    'pdf_render_timeout' => (int) env('PDF_RENDER_TIMEOUT', 110),
];
