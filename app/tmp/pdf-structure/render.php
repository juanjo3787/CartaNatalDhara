<?php

use App\Http\Controllers\ChartController;
use App\Models\Chart;
use App\Services\PhaseOneReportService;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$chart = Chart::with(['person', 'birthData.place', 'interpretations'])->findOrFail(4);
$report = app(PhaseOneReportService::class)->build($chart);
$wheel = 'data:image/jpeg;base64,'.base64_encode(file_get_contents(__DIR__.'/wheel.jpg'));
$method = new ReflectionMethod(ChartController::class, 'renderReportPdf');
file_put_contents(__DIR__.'/report.pdf', $method->invoke(app(ChartController::class), $chart, $report, $wheel));
