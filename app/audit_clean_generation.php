<?php

use App\Models\Chart;
use App\Services\Doors\AbstractDoorPipeline;
use App\Services\PhaseOneAiContentService;
use App\Services\PhaseOneAiGenerationService;
use App\Services\PhaseOneReportService;
use App\Services\ReportPdfService;
use App\Services\ReportStageMerger;
use App\Services\ReportState;
use App\Services\ReportTrace;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$directory = __DIR__.'/storage/app/private/report-audit';
$database = $directory.'/clean.sqlite';
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'cache.default' => 'file', 'cache.stores.file.path' => $directory.'/cache', 'reports.trace_structure' => true]);
DB::purge();
if (! file_exists($database)) {
    touch($database);
    Artisan::call('migrate', ['--force' => true]);
    [$source] = unserialize(file_get_contents($directory.'/source.ser'));
    $place = $source->birthData->place->replicate();
    $place->save();
    $person = $source->person->replicate();
    $person->residence_place_id = null;
    $person->save();
    $birth = $source->birthData->replicate();
    $birth->person_id = $person->id;
    $birth->place_id = $place->id;
    $birth->save();
    $chart = $source->replicate();
    $chart->person_id = $person->id;
    $chart->birth_data_id = $birth->id;
    $chart->phase_one_pdf = null;
    $chart->phase_one_pdf_generated_at = null;
    $chart->save();
    if ($source->snapshot !== $chart->snapshot) {
        throw new RuntimeException('Canonical snapshot changed.');
    }
}
$chart = Chart::firstOrFail();
$service = app(PhaseOneAiGenerationService::class);
$door = $argv[1] ?? 'descendente';
$session = 'clean-structure-audit-v2-'.$door;
if ($door === 'trace-excess') {
    $context = app(PhaseOneReportService::class)->contextForDoor($chart, 'descendente');
    $originalCount = $chart->interpretations()->count();
    $completed = [];
    foreach ($chart->interpretations()->where('door', 'descendente')->whereIn('block', ['function', 'sign', 'house', 'ruler', 'integration'])->get() as $row) {
        $completed[$row->block] = ['paragraphs' => array_map(fn ($text) => html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8'), preg_split('/\R{2,}/', $row->content))];
    }
    foreach ([1, 2] as $iteration) {
        $context['trace_id'] = 'descendant-excess-audit-'.$iteration;
        unset($completed['excess']);
        foreach (['development', 'characteristics', 'guidelines', 'examples_1', 'examples_2'] as $part) {
            echo 'iteration '.$iteration.' excess_'.$part.PHP_EOL;
            $result = app(PhaseOneAiContentService::class)->generateSunStage('excess_'.$part, $context, $completed);
            $completed = ReportStageMerger::merge($completed, $result);
            ReportTrace::record('draft', $completed['excess'], ['chart_id' => $chart->id, 'trace_id' => $context['trace_id'], 'section_id' => 'descendente.excess']);
        }
        $blocks = ReportState::render($completed['excess'], 'excess');
        DB::transaction(fn () => $chart->interpretations()->where('door', 'descendente')->where('block', 'excess')->update(['content' => implode("\n\n", $blocks)]));
        ReportTrace::record('persistence', $completed['excess'], ['chart_id' => $chart->id, 'trace_id' => $context['trace_id'], 'section_id' => 'descendente.excess']);
        if ($chart->interpretations()->count() !== $originalCount) {
            throw new RuntimeException('Unexpected appended interpretation.');
        }
        $report = app(PhaseOneReportService::class)->build($chart);
        foreach ([false, true] as $pdf) {
            $html = view('reports.phase-one.template', compact('chart', 'report', 'pdf'))->render();
            file_put_contents($directory.'/trace-'.$iteration.'-'.($pdf ? 'pdf' : 'web').'.html', $html);
        }
        echo 'iteration '.$iteration.' persisted once, 7/7/7, DOM rendered'.PHP_EOL;
    }
    exit;
}
if ($door === 'render') {
    $report = app(PhaseOneReportService::class)->build($chart);
    file_put_contents($directory.'/clean-model.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents($directory.'/clean.html', view('charts.report', compact('chart', 'report') + ['pdf' => true, 'wheelImage' => $chart->natal_wheel_image])->render());
    file_put_contents($directory.'/clean.pdf', app(ReportPdfService::class)->render($chart, $report, $chart->natal_wheel_image));
    echo 'Rendered clean report'.PHP_EOL;
    exit;
}
foreach (AbstractDoorPipeline::STAGES as $stage) {
    echo $door.'.'.$stage.' starting'.PHP_EOL;
    $result = $service->generateDoorStage($chart, $door, $stage, $session);
    echo json_encode($result).PHP_EOL;
}
