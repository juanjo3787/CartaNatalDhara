<?php

namespace Tests\Feature;

use App\Models\BirthData;
use App\Models\Chart;
use App\Models\Person;
use App\Models\Place;
use App\Models\ReportJob;
use App\Models\User;
use App\Services\PhaseOneReportService;
use App\Services\ReportPdfRefreshService;
use App\Services\ReportPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportPdfRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
    }

    private function savedChart(): Chart
    {
        $person = Person::create(['alias' => 'Refresh '.uniqid(), 'full_name' => 'Persona de prueba']);
        $place = Place::create(['city' => 'Madrid', 'country' => 'ES', 'latitude' => 40.4, 'longitude' => -3.7, 'timezone_identifier' => 'Europe/Madrid']);
        $birth = BirthData::create(['person_id' => $person->id, 'place_id' => $place->id, 'local_date' => '1986-09-25', 'local_time' => '21:30:00', 'timezone_identifier' => 'Europe/Madrid', 'utc_offset' => '+02:00', 'utc_datetime' => '1986-09-25 19:30:00']);
        $snapshot = ['houses' => []];
        foreach (range(1, 12) as $house) {
            $snapshot['houses'][$house] = ['longitude' => ($house - 1) * 30, 'sign' => 'aries', 'degrees' => 0, 'minutes' => 0, 'seconds' => 0];
        }
        foreach (['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto', 'ascendant', 'descendant', 'midheaven', 'true_node', 'mean_apogee'] as $point) {
            $snapshot[$point] = ['longitude' => 185, 'sign' => 'libra', 'degrees' => 5, 'minutes' => 0, 'seconds' => 0];
        }
        $chart = Chart::create(['person_id' => $person->id, 'birth_data_id' => $birth->id, 'configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus'], 'snapshot' => $snapshot, 'engine_version' => 'fixture', 'status' => 'calculated', 'natal_wheel_image' => 'data:image/jpeg;base64,'.base64_encode(file_get_contents(__DIR__.'/../Fixtures/wheel.jpg'))]);
        $service = app(PhaseOneReportService::class);
        $service->persistGeneratedContent($chart, $service->build($chart));
        $chart->update(['phase_one_pdf' => 'pdfs/'.$chart->id.'/old-v2.pdf']);
        Storage::disk('local')->put($chart->phase_one_pdf, '%PDF-old-test');

        return $chart;
    }

    public function test_refresh_preserves_saved_content_and_downloads_new_pdf_without_ai(): void
    {
        $chart = $this->savedChart();
        if (getenv('PDF_REFRESH_WHEEL_FILE')) {
            $chart->update(['natal_wheel_image' => 'data:image/jpeg;base64,'.base64_encode(file_get_contents(getenv('PDF_REFRESH_WHEEL_FILE')))]);
        }
        $chart->interpretations()->whereNull('door')->where('block', 'shared_intro')->update(['content' => '<p>Introducción editada manualmente y conservada.</p>']);
        $chart->interpretations()->where('door', 'sol')->where('block', 'integration')->update(['content' => '<p>Integración individual editada y conservada.</p>']);
        $before = $chart->interpretations()->orderBy('id')->get()->toArray();
        $snapshot = $chart->snapshot;
        $birth = $chart->birthData->getAttributes();
        $old = $chart->phase_one_pdf;
        $result = app(ReportPdfRefreshService::class)->refresh($chart);
        $this->assertSame('%PDF-old-test', Storage::disk('local')->get($result['backup']));
        $this->assertSame('%PDF-old-test', Storage::disk('local')->get($old));
        $this->assertSame($result['path'], $chart->fresh()->phase_one_pdf);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($result['path']));
        $this->assertSame($before, $chart->interpretations()->orderBy('id')->get()->toArray());
        $this->assertSame($snapshot, $chart->fresh()->snapshot);
        $this->assertSame($birth, $chart->birthData->fresh()->getAttributes());
        $report = app(PhaseOneReportService::class)->build($chart, storedOnly: true);
        $this->assertCount(4, $report['doors']);
        $this->assertArrayNotHasKey('integration', $report['sections']);
        $this->assertNotContains('Integración de las cuatro puertas', array_column($report['index'], 'title'));
        $wheelImage = $chart->natal_wheel_image;
        foreach ([false, true] as $pdf) {
            $html = view('reports.phase-one.template', compact('chart', 'report', 'pdf', 'wheelImage'))->render();
            $this->assertStringContainsString('Matriz de aspectos', $html);
            $this->assertStringContainsString('data-aspect-cell', $html);
            $this->assertStringContainsString('☌', $html);
            $this->assertStringContainsString('report-cover-visuals', $html);
            $this->assertStringContainsString($pdf ? 'alt="Rueda astrológica"' : 'data-natal-wheel', $html);
            $this->assertStringNotContainsString('Integración de las cuatro puertas', $html);
            $this->assertStringContainsString('Introducción editada manualmente y conservada.', $html);
            $this->assertStringContainsString('Integración individual editada y conservada.', $html);
            foreach (['sol', 'luna', 'ascendente', 'descendente'] as $door) {
                $this->assertStringContainsString('data-door-key="'.$door.'"', $html);
                $this->assertStringContainsString('data-section-id="'.$door.'.harmonization"', $html);
            }
        }
        $this->actingAs(User::factory()->create())->get(route('charts.report.download', $chart))
            ->assertOk()->assertStreamedContent(Storage::disk('local')->get($result['path']));
        $this->assertDatabaseCount('report_jobs', 0);
        $this->assertDatabaseCount('report_generations', 1);
        Http::assertNothingSent();

        if (getenv('PDF_REFRESH_QA_DIR')) {
            $directory = getenv('PDF_REFRESH_QA_DIR');
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($directory.'/refreshed.pdf', Storage::disk('local')->get($result['path']));
            file_put_contents($directory.'/report.html', view('charts.report', compact('chart', 'report'))->render());
            file_put_contents($directory.'/pdf.html', view('charts.report', compact('chart', 'report', 'wheelImage') + ['pdf' => true])->render());
            file_put_contents($directory.'/chart.html', view('charts.show', compact('chart'))->render());
        }
    }

    public function test_web_report_reuses_the_saved_lossless_wheel_image(): void
    {
        $chart = $this->savedChart();
        $wheelImage = 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\nwheel-image");
        $chart->update(['natal_wheel_image' => $wheelImage]);

        $this->actingAs(User::factory()->create())
            ->get(route('charts.report', $chart))
            ->assertOk()
            ->assertSee('class="report-cover-wheel-container nocturna-wheel-container"', false)
            ->assertSee('src="'.$wheelImage.'"', false)
            ->assertDontSee('data-natal-wheel');
    }

    public function test_index_numbering_expansion_closure_technical_data_and_pdf_only_blank_page(): void
    {
        $chart = $this->savedChart();
        $report = app(PhaseOneReportService::class)->build($chart);

        $this->assertSame(
            ['01', '02', '03', '04', '05', '06', '07'],
            array_column($report['index'], 'number'),
        );
        $this->assertSame(
            ['sol', 'luna', 'ascendente', 'descendente', 'practice', 'expansion', 'technical'],
            array_column($report['index'], 'key'),
        );
        $this->assertCount(9, $report['expansion_closing']['paragraphs']);
        $this->assertCount(12, $report['expansion_closing']['houses']);
        $this->assertCount(12, $report['technical']['houses_table']);
        $wheelImage = $chart->natal_wheel_image;

        foreach ([false, true] as $pdf) {
            $html = view('reports.phase-one.template', compact('chart', 'report', 'pdf', 'wheelImage'))->render();
            $this->assertStringContainsString('href="#report-section-practice"', $html);
            $this->assertStringContainsString('href="#report-section-expansion"', $html);
            $this->assertStringContainsString('href="#report-section-technical"', $html);
            $this->assertStringContainsString('05. Tu práctica de autoobservación', $html);
            $this->assertStringContainsString('06. Ampliación de tu mapa interior', $html);
            $this->assertStringContainsString('07. Datos de la carta', $html);
            $this->assertSame(3, substr_count($html, 'class="report-door-heading report-chapter-heading"><h1>'));
            $this->assertStringContainsString('<strong>¿Y si el bloqueo que sientes', $html);
            $this->assertStringContainsString('<strong>Ahora, la verdadera pregunta es:', $html);
            $this->assertStringContainsString('Casa 12 — Inconsciente', $html);
            $this->assertStringContainsString('Posiciones planetarias', $html);
            $this->assertStringContainsString('Cúspides de casas', $html);
            $this->assertSame($pdf, str_contains($html, 'report-pdf-blank-page'));
            $practicePosition = strpos($html, '05. Tu práctica de autoobservación');
            $expansionPosition = strpos($html, '06. Ampliación de tu mapa interior');
            $technicalPosition = strpos($html, '07. Datos de la carta');
            $this->assertLessThan($expansionPosition, $practicePosition);
            $this->assertLessThan($technicalPosition, $expansionPosition);
            foreach ($report['expansion_closing']['houses'] as $index => $house) {
                $this->assertStringContainsString('Casa '.($index + 1).' — '.$house, $html);
            }
            if ($pdf) {
                $indexPosition = strpos($html, 'Recorrido del dossier');
                $firstBlankPagePosition = strpos($html, 'report-pdf-blank-page');
                $secondBlankPagePosition = strpos($html, 'report-pdf-blank-page', $firstBlankPagePosition + 1);
                $coverPosition = strpos($html, 'Primer informe de la fase 1');
                $contentPosition = strpos($html, 'Tu primera lectura');
                $this->assertLessThan($firstBlankPagePosition, $coverPosition);
                $this->assertLessThan($indexPosition, $firstBlankPagePosition);
                $this->assertLessThan($secondBlankPagePosition, $indexPosition);
                $this->assertLessThan($contentPosition, $secondBlankPagePosition);
                $this->assertSame(2, substr_count($html, 'report-pdf-blank-page'));
            }
        }
    }

    public function test_dry_run_reports_missing_blocks_without_defaults_or_writes(): void
    {
        $ready = $this->savedChart();
        $missing = $this->savedChart();
        $missing->interpretations()->where('door', 'sol')->where('block', 'integration')->delete();
        $before = Storage::disk('local')->allFiles();
        $this->artisan('reports:refresh-pdfs', ['--all' => true, '--dry-run' => true])
            ->expectsOutput('Carta '.$ready->id.': preparada.')
            ->expectsOutput('Carta '.$missing->id.': omitida. Faltan bloques guardados: sol.integration')
            ->expectsOutput('Preparadas: 1. Omitidas: 1.')->assertExitCode(1);
        $this->assertSame($before, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('report_generations', 0);
        Http::assertNothingSent();
    }

    public function test_render_failure_leaves_previous_download_untouched(): void
    {
        $chart = $this->savedChart();
        $this->mock(ReportPdfService::class)->shouldReceive('render')->once()->andThrow(new \RuntimeException('Render fallido'));
        $this->artisan('reports:refresh-pdfs', ['chart' => $chart->id])
            ->expectsOutput('Carta '.$chart->id.': omitida. Render fallido')->assertExitCode(1);
        $this->assertSame($chart->phase_one_pdf, $chart->fresh()->phase_one_pdf);
        $this->assertSame([$chart->phase_one_pdf], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('report_generations', 0);
    }

    public function test_active_jobs_and_missing_wheel_are_skipped(): void
    {
        $chart = $this->savedChart();
        $chart->update(['natal_wheel_image' => null]);
        $this->artisan('reports:refresh-pdfs', ['chart' => $chart->id])->assertExitCode(1);
        ReportJob::create(['chart_id' => $chart->id, 'active_chart_id' => $chart->id, 'user_id' => User::factory()->create()->id, 'status' => 'queued', 'doors' => ['sol']]);
        $this->artisan('reports:refresh-pdfs', ['chart' => $chart->id])
            ->expectsOutput('Carta '.$chart->id.': omitida. Hay una generación activa. Espera a que termine.')->assertExitCode(1);
        $this->assertDatabaseCount('report_generations', 0);
        Http::assertNothingSent();
    }

    public function test_command_requires_an_unambiguous_selection(): void
    {
        $this->artisan('reports:refresh-pdfs')->assertExitCode(2);
        $this->artisan('reports:refresh-pdfs', ['chart' => 1, '--all' => true])->assertExitCode(2);
        $this->artisan('reports:refresh-pdfs', ['chart' => 'invalid'])->assertExitCode(2);
        $this->artisan('reports:refresh-pdfs', ['chart' => 999])->assertExitCode(1);

    }

    public function test_missing_astrological_data_is_not_replaced_with_defaults(): void
    {
        $chart = $this->savedChart();
        $snapshot = $chart->snapshot;
        unset($snapshot['venus']);
        $chart->update(['snapshot' => $snapshot]);
        $this->artisan('reports:refresh-pdfs', ['chart' => $chart->id])
            ->expectsOutput('Carta '.$chart->id.': omitida. Falta el dato astrológico guardado: venus.longitude')
            ->assertExitCode(1);
        $this->assertSame($snapshot, $chart->fresh()->snapshot);
        $this->assertSame($chart->phase_one_pdf, $chart->fresh()->phase_one_pdf);
        Http::assertNothingSent();
    }

    public function test_pdf_cover_stacks_wheel_above_matrix_with_cream_background(): void
    {
        $template = file_get_contents(resource_path('views/reports/phase-one/template.blade.php'));
        $style = substr($template, strpos($template, '<style>'), strpos($template, '</style>') + 8 - strpos($template, '<style>'));
        $style = Blade::render($style, ['pdf' => true]);
        $this->assertStringContainsString('.report-cover-visuals { width: 100%;', $style);
        $this->assertStringContainsString('.report-cover-wheel-cell, .report-cover-matrix-cell { width: 100%; }', $style);
        $this->assertStringContainsString('background: #fffdf9 !important;', $style);
        $this->assertStringContainsString('width: 65mm;', $style);
        $this->assertStringContainsString('height: 65mm;', $style);
        $this->assertStringContainsString('.report-cover .aspect-section--cover .aspect-positions { display: block;', $style);
    }
}
