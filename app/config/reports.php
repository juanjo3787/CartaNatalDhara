<?php

return [
    'trace_structure' => (bool) env('REPORT_TRACE_STRUCTURE', false),
    // PDF rendering runs inside the report worker's per-stage execution budget.
    'pdf_render_timeout' => (int) env('PDF_RENDER_TIMEOUT', 110),
];
