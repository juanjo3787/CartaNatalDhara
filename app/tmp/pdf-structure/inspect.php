<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$chart = App\Models\Chart::with(['person', 'birthData.place', 'interpretations'])->findOrFail(4);
$report = app(App\Services\PhaseOneReportService::class)->build($chart);
$door = collect($report['doors'])->firstWhere('key', 'descendente');
foreach ($door['blocks'] as $key => $values) {
    echo "\n=== {$key} ".count($values)."\n";
    foreach (array_slice($values, -3) as $value) {
        echo substr(strip_tags($value), 0, 220)."\n";
    }
}
