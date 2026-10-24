<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use App\Services\InteriorMapMaster;
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
            $this->assertSame(1, substr_count($html, 'data-section-id="sol.concepts"'));
            $this->assertSame(1, substr_count($html, 'data-index-key="opposites"'));
            $this->assertStringContainsString('BLOQUE I — El Sol', $html);
            $this->assertStringContainsString('BLOQUE V — Los opuestos complementarios', $html);
            $this->assertStringNotContainsString('Sol natal', $html);
            $this->assertStringContainsString("content: counter(report-characteristic) '. '", $html);
            $this->assertStringNotContainsString("content: 'CONCEPTO: '", $html);
            $this->assertStringNotContainsString("content: 'Característica '", $html);
            $this->assertStringNotContainsString('data-report-blank-page="9"', $html);
            $this->assertStringContainsString('#f8dfcc', $html);
            $this->assertStringContainsString('Inicio de la casa 7', $html);
            $this->assertStringContainsString('<h3>Concepto sol 1</h3><h4>Característica sol 1 1</h4><p>', $html);
            $this->assertStringNotContainsString('Tu primera lectura', $html);
            $this->assertStringNotContainsString('<strong>DON:</strong>', $html);
            $this->assertStringNotContainsString('Expresión desarmónica', $html);
            $this->assertStringNotContainsString('La integración de tus cuatro puertas', $html);
        }
        $editable = $service->editableContent($chart);
        $chart->update(['phase_one_pdf' => 'obsolete.pdf']);
        app(PhaseOneManualSaveService::class)->save($chart, $editable['shared'], $editable['doors']);
        $this->assertNull($chart->fresh()->phase_one_pdf);
        $this->assertTrue($service->build($chart->fresh(), storedOnly: true)['complete']);
        $this->assertSame($editable['shared']['concepts'], $service->editableContent($chart->fresh())['shared']['concepts']);
        $this->actingAs(User::factory()->create())->get(route('charts.report.edit', $chart))->assertOk()->assertSee('Los opuestos complementarios')->assertSee('ql-header', false);
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
        $previous = $editable['doors']['sol']['concepts'];
        $editable['doors']['sol']['concepts'] .= '<p>Tu Sol en Libra expresa tu identidad.</p>';
        $this->actingAs(User::factory()->create())->put(route('charts.report.save', $chart), $editable)
            ->assertSessionHasErrors('doors');
        $this->assertSame($previous, app(PhaseOneReportService::class)->editableContent($chart->fresh())['doors']['sol']['concepts']);
    }

    public function test_manual_edit_cannot_replace_explanations_with_a_list(): void
    {
        $chart = $this->chart();
        $this->saveChapters($chart);
        $editable = app(PhaseOneReportService::class)->editableContent($chart);
        $editable['doors']['sol']['concepts'] = '<h3>Concepto</h3><ul><li>Amable</li><li>Sensible</li></ul>';
        $this->actingAs(User::factory()->create())->put(route('charts.report.save', $chart), $editable)
            ->assertSessionHasErrors('doors');
    }

    public function test_manual_edit_distinguishes_conceptual_mentions_from_later_false_claims(): void
    {
        $chart = $this->chart();
        $this->saveChapters($chart);
        $service = app(PhaseOneReportService::class);
        $editable = $service->editableContent($chart);
        $editable['shared']['concepts'] .= '<p>La polaridad no exige Luna en Acuario.</p>';
        app(PhaseOneManualSaveService::class)->save($chart, $editable['shared'], $editable['doors']);
        $saved = $service->editableContent($chart->fresh());
        $this->assertStringContainsString('no exige Luna en Acuario', $saved['shared']['concepts']);

        $editable = $saved;
        $editable['shared']['concepts'] .= '<p>No es un diagnóstico, pero tu Sol en Libra expresa tu identidad.</p>';
        $this->actingAs(User::factory()->create())->put(route('charts.report.save', $chart), $editable)
            ->assertSessionHasErrors('shared');
        $this->assertSame($saved, $service->editableContent($chart->fresh()));
    }

    public function test_manual_edit_checks_block_v_and_cross_block_duplicates_before_saving(): void
    {
        $chart = $this->chart();
        $this->saveChapters($chart);
        $service = app(PhaseOneReportService::class);
        $original = $service->editableContent($chart);
        $editable = $original;
        $editable['shared']['concepts'] .= '<p>Plutón está en Aries.</p>';
        $this->actingAs(User::factory()->create())->put(route('charts.report.save', $chart), $editable)
            ->assertSessionHasErrors('shared');
        $editable = $original;
        $editable['shared']['concepts'] = $original['doors']['sol']['concepts'];
        $this->put(route('charts.report.save', $chart), $editable)->assertSessionHasErrors('doors');
        $this->assertSame($original, $service->editableContent($chart->fresh()));
    }

    public function test_birth_time_uncertainty_is_provided_without_inventing_precision(): void
    {
        $chart = $this->chart();
        $chart->birthData->update(['time_precision' => 'approximate', 'time_source' => 'estimated']);
        $facts = app(InteriorMapMaster::class)->facts($chart);
        $this->assertTrue($facts['technical']['angles_houses_conditional']);
        $this->assertSame('estimated', $facts['technical']['time_source']);
        $chart->birthData->update(['time_precision' => 'exact', 'time_source' => 'document']);
        $this->assertFalse(app(InteriorMapMaster::class)->facts($chart)['technical']['angles_houses_conditional']);
    }

    public function test_editor_preserves_inline_emphasis_and_removes_html_attributes(): void
    {
        $chart = $this->chart();
        $this->saveChapters($chart);
        $service = app(PhaseOneReportService::class);
        $editable = $service->editableContent($chart);
        $editable['doors']['sol']['concepts'] .= '<p onclick="invalid()"><strong>Un matiz propio</strong> conserva su énfasis.</p>';
        app(PhaseOneManualSaveService::class)->save($chart, $editable['shared'], $editable['doors']);
        $saved = $service->editableContent($chart->fresh())['doors']['sol']['concepts'];
        $this->assertStringContainsString('<p><strong>Un matiz propio</strong> conserva su énfasis.</p>', $saved);
        $this->assertStringNotContainsString('onclick', $saved);
    }
}
