<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$chart, $report] = unserialize(file_get_contents(__DIR__.'/storage/app/private/report-audit/source.ser'));
$html = view('charts.report', compact('chart', 'report') + ['pdf' => true, 'wheelImage' => $chart->natal_wheel_image])->render();
file_put_contents(__DIR__.'/storage/app/private/report-audit/before.html', $html);
$controller = app(App\Http\Controllers\ChartController::class);
$method = new ReflectionMethod($controller, 'renderReportPdf');
$pdf = $method->invoke($controller, $chart, $report, $chart->natal_wheel_image);
file_put_contents(__DIR__.'/storage/app/private/report-audit/before.pdf', $pdf);
echo strlen($pdf).PHP_EOL;
