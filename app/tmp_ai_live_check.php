<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$chart = App\Models\Chart::first();
if (! $chart) {
    echo "NO_CHART\n";
    exit(0);
}

foreach (['sol', 'luna', 'ascendente', 'descendente'] as $door) {
    try {
        $service = app(App\Services\PhaseOneAiGenerationService::class);
        $count = $service->generateDoor($chart, $door);
        echo $door . ':' . $count . PHP_EOL;
    } catch (Throwable $e) {
        echo $door . ':ERROR:' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}
