<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$chart, $report] = unserialize(file_get_contents(__DIR__.'/storage/app/private/report-audit/source.ser'));
foreach ($report['doors'] as &$door) {
    foreach (array_keys(App\Services\ReportState::HEADINGS) as $stateName) {
        $original = $door['blocks'][$stateName];
        $state = App\Services\ReportState::fromRendered($original, $door['key'].'.'.$stateName, $stateName);
        $door['states'][$stateName] = $state;
        $door['blocks'][$stateName] = App\Services\ReportState::render($state, $stateName);
        if ($original !== $door['blocks'][$stateName]) {
            throw new RuntimeException('Content changed: '.$door['key'].'.'.$stateName);
        }
        echo $door['key'].'.'.$stateName.': lossless, 7/7/7'.PHP_EOL;
    }
}
unset($door);
$html = view('charts.report', compact('chart', 'report') + ['pdf' => true, 'wheelImage' => $chart->natal_wheel_image])->render();
file_put_contents(__DIR__.'/storage/app/private/report-audit/after.html', $html);
$controller = app(App\Http\Controllers\ChartController::class);
$method = new ReflectionMethod($controller, 'renderReportPdf');
$pdf = $method->invoke($controller, $chart, $report, $chart->natal_wheel_image);
file_put_contents(__DIR__.'/storage/app/private/report-audit/after.pdf', $pdf);
echo strlen($pdf).PHP_EOL;
