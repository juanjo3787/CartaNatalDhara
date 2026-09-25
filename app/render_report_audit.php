<?php

use App\Services\ReportPdfService;
use App\Services\ReportState;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$chart, $report] = unserialize(file_get_contents(__DIR__.'/storage/app/private/report-audit/source.ser'));
foreach ($report['doors'] as &$door) {
    foreach (array_keys(ReportState::HEADINGS) as $stateName) {
        $original = $door['blocks'][$stateName];
        $state = ReportState::fromRendered($original, $door['key'].'.'.$stateName, $stateName);
        $door['states'][$stateName] = $state;
        $door['blocks'][$stateName] = ReportState::render($state, $stateName);
        if ($original !== $door['blocks'][$stateName]) {
            throw new RuntimeException('Content changed: '.$door['key'].'.'.$stateName);
        }
        echo $door['key'].'.'.$stateName.': lossless, 7/7/7'.PHP_EOL;
    }
}
unset($door);
$html = view('charts.report', compact('chart', 'report') + ['pdf' => true, 'wheelImage' => $chart->natal_wheel_image])->render();
file_put_contents(__DIR__.'/storage/app/private/report-audit/after.html', $html);
$web = view('reports.phase-one.template', compact('chart', 'report') + ['pdf' => false])->render();
file_put_contents(__DIR__.'/storage/app/private/report-audit/web.html', '<!doctype html><html lang="es"><meta charset="UTF-8"><style>*{box-sizing:border-box}body{margin:0;background:#eee}</style>'.$web.'</html>');
$pdf = app(ReportPdfService::class)->render($chart, $report, $chart->natal_wheel_image);
file_put_contents(__DIR__.'/storage/app/private/report-audit/after.pdf', $pdf);
echo strlen($pdf).PHP_EOL;
