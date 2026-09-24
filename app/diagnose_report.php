<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $chart = App\Models\Chart::findOrFail(4);
    $report = app(App\Services\PhaseOneReportService::class)->build($chart);
    @mkdir(__DIR__.'/storage/app/private/report-audit', 0777, true);
    file_put_contents(__DIR__.'/storage/app/private/report-audit/source.ser', serialize([$chart, $report]));
    foreach ($chart->reportGenerations()->where('ai_assisted', true)->get() as $generation) {
        echo json_encode(['generation_id' => $generation->id, 'door' => $generation->door, 'created_at' => $generation->created_at, 'staged_prompt' => str_contains($generation->user_prompt ?? '', 'excess_development')], JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
    foreach (App\Models\Chart::query()->latest('id')->limit(5)->get() as $chart) {
        echo json_encode(['chart_id' => $chart->id, 'interpretations' => $chart->interpretations()->count()], JSON_UNESCAPED_UNICODE).PHP_EOL;
        foreach ($chart->interpretations()->where('block', 'excess')->get() as $row) {
            echo json_encode(['id' => $row->id, 'door' => $row->door, 'ai' => $row->ai_assisted, 'template' => $row->template_id, 'start' => mb_substr($row->content, 0, 240), 'headings' => substr_count($row->content, 'Características que puedes observar'), 'terms' => str_contains($row->content, 'Antes de aplicar símbolos'), 'practice' => str_contains($row->content, 'Práctica y recursos para no sobredimensionar')], JSON_UNESCAPED_UNICODE).PHP_EOL;
        }
    }
} catch (Throwable $e) { echo get_class($e).': '.preg_replace('/password[^ ]*/i', '[redacted]', $e->getMessage()).PHP_EOL; exit(1); }
