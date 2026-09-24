<?php

use App\Models\Chart;
use App\Services\PhaseOneReportService;
use App\Services\ReportStageMerger;
use App\Services\ReportState;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = __DIR__.'/storage/app/private/report-audit';
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $directory.'/clean.sqlite']);
DB::purge();
$chart = Chart::firstOrFail();
$raw = [];
foreach (glob(__DIR__.'/storage/app/private/report-traces/*.json') as $file) {
    $record = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $meta = $record['meta'];
    $trace = $meta['trace_id'] ?? '';
    if (! str_starts_with($trace, 'descendant-excess-audit-')) {
        continue;
    }
    $stage = $meta['stage'];
    if (($meta['attempt'] ?? 0) >= ($raw[$trace][$stage]['attempt'] ?? 0)) {
        $raw[$trace][$stage] = ['attempt' => $meta['attempt'], 'data' => json_decode($record['raw_response_content'], true, 512, JSON_THROW_ON_ERROR)];
    }
}
foreach ([1, 2] as $iteration) {
    $trace = 'descendant-excess-audit-'.$iteration;
    $state = [];
    foreach (['development', 'characteristics', 'guidelines', 'examples_1', 'examples_2'] as $part) {
        $state = ReportStageMerger::merge($state, $raw[$trace]['excess_'.$part]['data'] ?? throw new RuntimeException('RAW missing: '.$trace.'.'.$part));
    }
    ReportState::validate($state['excess'], 'descendente.excess');
    foreach (['web', 'pdf'] as $mode) {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($directory.'/trace-'.$iteration.'-'.$mode.'.html'));
        $xpath = new DOMXPath($dom);
        $body = $xpath->query('//*[@data-section-id="descendente.excess"]/div[@class="report-block-body"]')->item(0);
        $blocks = [];
        foreach ($body->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $blocks[] = $dom->saveHTML($node);
            }
        }
        $actual = ReportState::fromRendered($blocks, 'descendente.excess', 'excess');
        foreach (['development', 'characteristics', 'guidelines', 'examples'] as $key) {
            if ($actual[$key] !== $state['excess'][$key]) {
                throw new RuntimeException('RAW/DOM mismatch: '.$trace.'.'.$mode.'.'.$key);
            }
        }
    }
    echo $trace.': RAW = normalized = web DOM = PDF DOM, 7/7/7'.PHP_EOL;
}
$report = app(PhaseOneReportService::class)->build($chart);
$descendant = collect($report['doors'])->firstWhere('key', 'descendente')['states']['excess'];
foreach (['development', 'characteristics', 'guidelines', 'examples'] as $key) {
    if ($descendant[$key] !== $state['excess'][$key]) {
        throw new RuntimeException('Latest persisted state does not replace previous generation.');
    }
}
[$original] = unserialize(file_get_contents($directory.'/source.ser'));
if ($chart->snapshot !== $original->snapshot || $chart->interpretations()->count() !== 44) {
    throw new RuntimeException('Snapshot changed or blocks appended.');
}
echo 'DB = latest RAW; document model = DB; exactly 44 blocks; canonical snapshot unchanged.'.PHP_EOL;
