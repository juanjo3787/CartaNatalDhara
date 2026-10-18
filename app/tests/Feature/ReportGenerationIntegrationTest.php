<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use App\Services\PdfPageGeometry;
use App\Services\PhaseOneManualSaveService;
use App\Services\PhaseOneReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\InteriorMapFixture;
use Tests\TestCase;

final class ReportGenerationIntegrationTest extends TestCase
{
    use InteriorMapFixture, RefreshDatabase;

    public function test_fresh_chart_has_no_invented_or_example_interpretation(): void
    {
        $chart = $this->chart();
        $this->actingAs(User::factory()->create())->get(route('charts.report', $chart))
            ->assertOk()->assertSee('todavía no está generado')->assertDontSee('Pastora');
        $report = app(PhaseOneReportService::class)->build($chart);
        $this->assertFalse($report['complete']);
        $this->assertSame([], $report['doors'][0]['blocks']);
        $this->assertDatabaseCount('interpretations', 0);
        $this->expectExceptionMessage('Faltan secciones');
        app(PhaseOneReportService::class)->build($chart, storedOnly: true);
    }

    public function test_web_pdf_and_editor_share_new_sections_and_edits_invalidate_pdf(): void
    {
        $chart = $this->chart();
        $this->saveChapters($chart);
        $service = app(PhaseOneReportService::class);
        $report = $service->build($chart, storedOnly: true);
        foreach ([false, true] as $pdf) {
            $html = view('reports.phase-one.template', compact('chart', 'report', 'pdf'))->render();
            $this->assertSame(1, substr_count($html, 'data-section-id="sol.harmony"'));
            $this->assertSame(1, substr_count($html, 'data-index-key="integration"'));
            $this->assertStringNotContainsString('El regente y su posición', $html);
            $this->assertStringNotContainsString('data-report-blank-page="9"', $html);
            $this->assertStringContainsString('#f8dfcc', $html);
            $this->assertStringContainsString('Las características de Cáncer', $html);
            $this->assertStringContainsString('Tu opuesto complementario Capricornio', $html);
            $this->assertStringContainsString('Inicio de la casa 7', $html);
            $this->assertStringContainsString('<h3>Tema primero</h3>', $html);
            $this->assertStringContainsString('<h4>Puntos de trabajo</h4><ul><li>', $html);
            $this->assertStringContainsString('<strong>DON:</strong>', $html);
        }
        $editable = $service->editableContent($chart);
        $chart->update(['phase_one_pdf' => 'obsolete.pdf']);
        app(PhaseOneManualSaveService::class)->save($chart, $editable['shared'], $editable['doors']);
        $this->assertNull($chart->fresh()->phase_one_pdf);
        $this->assertTrue($service->build($chart->fresh(), storedOnly: true)['complete']);
        $this->assertSame($editable['shared']['themes'], $service->editableContent($chart->fresh())['shared']['themes']);
        $this->actingAs(User::factory()->create())->get(route('charts.report.edit', $chart))->assertOk()->assertSee('Tu opuesto complementario');
    }

    public function test_last_chart_deletion_removes_person_and_allows_name_reuse(): void
    {
        $chart = $this->chart();
        $person = $chart->person;
        $this->actingAs(User::factory()->create())->delete(route('charts.destroy', $chart))->assertRedirect();
        $this->assertModelMissing($chart);
        $this->assertModelMissing($person);
        $this->assertInstanceOf(Person::class, Person::create(['alias' => $person->alias, 'full_name' => $person->full_name]));
        $this->assertDatabaseCount('charts', 0);
    }

    public function test_download_uses_informe_i_and_current_chart_name(): void
    {
        Storage::fake('local');
        $chart = $this->chart();
        $this->saveChapters($chart);
        $path = 'pdfs/'.$chart->id.'/job-1-v'.PdfPageGeometry::VERSION.'.pdf';
        Storage::disk('local')->put($path, '%PDF-synthetic');
        $chart->update(['phase_one_pdf' => $path]);
        $response = $this->actingAs(User::factory()->create())->get(route('charts.report.download', $chart))->assertOk();
        $this->assertStringContainsString("filename*=utf-8''Informe%20I%20-%20Persona%20sint%C3%A9tica%20%C3%91.pdf", $response->headers->get('content-disposition'));
        $chart->update(['report_is_stale' => true]);
        $this->get(route('charts.report.download', $chart))->assertRedirect(route('charts.show', $chart));
    }

    public function test_manual_edit_cannot_save_a_conflicting_planetary_fact(): void
    {
        $chart = $this->chart();
        $this->saveChapters($chart);
        $editable = app(PhaseOneReportService::class)->editableContent($chart);
        $previous = $editable['doors']['sol']['sign'];
        $editable['doors']['sol']['sign'] .= '<p>Tu Sol en Libra expresa tu identidad.</p>';
        $this->actingAs(User::factory()->create())->put(route('charts.report.save', $chart), $editable)
            ->assertSessionHasErrors('doors');
        $this->assertSame($previous, app(PhaseOneReportService::class)->editableContent($chart->fresh())['doors']['sol']['sign']);
    }
}
