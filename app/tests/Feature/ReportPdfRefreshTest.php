<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InteriorMapMaster;
use App\Services\PhaseOneReportService;
use App\Services\ReportPdfRefreshService;
use App\Services\ReportPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\InteriorMapFixture;
use Tests\TestCase;

final class ReportPdfRefreshTest extends TestCase
{
    use InteriorMapFixture, RefreshDatabase;

    public function test_cover_fills_a4_with_larger_wheel_and_separate_text_regions(): void
    {
        Storage::fake('local');
        $chart = $this->chart();
        $chart->person->update(['full_name' => 'Pastora María Villarreal Muñiz']);
        $chart->birthData->place->update(['name' => 'Hospital Universitario Virgen Macarena']);
        $this->saveChapters($chart);
        $report = app(PhaseOneReportService::class)->build($chart, storedOnly: true);
        $html = view('reports.phase-one.template', ['chart' => $chart, 'report' => $report, 'pdf' => true])->render();
        $cover = explode('<section class="report-page report-reading report-contents">', $html)[0];
        $this->assertStringContainsString('<h2>Informe I</h2>', $cover);
        foreach (['Sol · Luna · Ascendente · Descendente', 'Opuestos complementarios', 'Identidad y voluntad', 'Necesidades emocionales', 'Ritmo propio y vínculos'] as $removed) {
            $this->assertStringNotContainsString($removed, $cover);
        }
        Log::spy();
        app(ReportPdfService::class)->store($chart->fresh(), 104);
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            if ($message !== 'Phase 1 PDF rendered.') {
                return false;
            }
            $regions = $context['cover_regions'];
            $this->assertEqualsWithDelta(0, $regions['page']['x'], 0.5);
            $this->assertEqualsWithDelta(0, $regions['page']['y'], 0.5);
            $this->assertEqualsWithDelta(595.28, $regions['page']['width'], 0.5);
            $this->assertEqualsWithDelta(841.89, $regions['page']['height'], 1);
            $this->assertEqualsWithDelta(138 * 72 / 25.4, $regions['wheel']['width'], 0.5);
            $this->assertGreaterThan(190 * 72 / 25.4, $regions['matrix']['y']);
            $previousBottom = 0;
            foreach (['heading', 'wheel', 'matrix', 'metadata'] as $key) {
                $region = $regions[$key];
                $this->assertSame(1, $region['page'], $key);
                $this->assertGreaterThan(0, $region['height'], $key);
                $this->assertGreaterThanOrEqual($previousBottom, $region['y'], 'Solapamiento en '.$key);
                $previousBottom = $region['y'] + $region['height'];
                $this->assertLessThan(841.89, $previousBottom, $key);
            }
            $this->assertSame(3, $context['index_pages']['sol']);
            $this->assertSame([], $context['blank_pages']);

            return true;
        })->once();
    }

    public function test_each_block_starts_on_a_new_page_without_blank_pages(): void
    {
        Storage::fake('local');
        $chart = $this->chart();
        foreach (InteriorMapMaster::CHAPTERS as $chapter) {
            $content = self::chapter($chapter);
            $content['concepts'] = [array_replace($content['concepts'][0], ['characteristics' => [
                ['title' => 'Un matiz propio de '.$chapter, 'paragraphs' => [$chapter.' '.str_repeat('Desarrollo sintético del contenido para verificar su continuación natural. ', 20)]],
            ]])];
            app(PhaseOneReportService::class)->storeChapter($chart, $chapter === 'opposites' ? null : $chapter, $content);
        }
        Log::spy();
        app(ReportPdfService::class)->store($chart, 103);
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            if ($message !== 'Phase 1 PDF rendered.') {
                return false;
            }
            foreach (array_slice(InteriorMapMaster::CHAPTERS, 1) as $index => $chapter) {
                $previousPages = $context['chapter_pages'][InteriorMapMaster::CHAPTERS[$index]];
                $this->assertSame([], array_intersect($previousPages, $context['chapter_pages'][$chapter]));
                $this->assertSame(max($previousPages) + 1, $context['index_pages'][$chapter]);
            }
            $this->assertSame([], $context['blank_pages']);
            $this->assertSame(3, $context['index_pages']['sol']);

            return true;
        })->once();
    }

    public function test_sections_have_extra_spacing_without_changing_paragraph_spacing(): void
    {
        $chart = $this->chart();
        $this->saveChapters($chart);
        $report = app(PhaseOneReportService::class)->build($chart, storedOnly: true);
        foreach ([false, true] as $pdf) {
            $html = view('reports.phase-one.template', compact('chart', 'report', 'pdf'))->render();
            $this->assertStringContainsString('.report-reading .report-block-body > ul + h3 { margin-top: 32pt; page-break-before: auto; }', $html);
            $this->assertStringContainsString('page-break-after: avoid', $html);
        }
        Storage::fake('local');
        Log::spy();
        app(ReportPdfService::class)->store($chart, 105);
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            if ($message !== 'Phase 1 PDF rendered.') {
                return false;
            }
            $this->assertNotEmpty($context['section_gaps_pt']);
            foreach ($context['section_gaps_pt'] as $gap) {
                $this->assertGreaterThanOrEqual(31.9, $gap, 'Separacion real entre palabras clave y el siguiente apartado.');
            }

            return true;
        })->once();
    }

    public function test_extended_blocks_have_real_index_pages_without_blank_pages_or_fixed_page_counts(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $chart = $this->chart();
        foreach (InteriorMapMaster::CHAPTERS as $chapter) {
            $content = self::chapter($chapter);
            $content['concepts'][0]['characteristics'][0]['paragraphs'][] = $chapter.' '.str_repeat('Desarrollo sintético extenso para comprobar el salto natural de página y la continuidad del contenido. ', 150);
            app(PhaseOneReportService::class)->storeChapter($chart, $chapter === 'opposites' ? null : $chapter, $content);
        }
        Log::spy();
        app(ReportPdfService::class)->store($chart, 102);
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            if ($message !== 'Phase 1 PDF rendered.') {
                return false;
            }
            $this->assertGreaterThan(7, $context['pages']);
            $this->assertSame(InteriorMapMaster::CHAPTERS, array_keys($context['index_pages']));
            $this->assertSame([], $context['blank_pages']);
            $this->assertEqualsCanonicalizing(range(1, $context['pages']), $context['content_pages']);
            $this->assertSame(3, $context['index_pages']['sol']);
            $starts = array_values($context['index_pages']);
            $sorted = $starts;
            sort($sorted);
            $this->assertSame($sorted, $starts);
            $this->assertCount(5, array_unique($starts));

            return true;
        })->once();
        Http::assertNothingSent();
    }

    public function test_refresh_renders_current_master_without_ai_or_changed_calculations(): void
    {
        Http::preventStrayRequests();
        Storage::fake('local');
        $chart = $this->chart();
        $this->saveChapters($chart);
        $chart->update(['phase_one_pdf' => 'pdfs/previous.pdf']);
        Storage::disk('local')->put('pdfs/previous.pdf', '%PDF-synthetic');
        $snapshot = $chart->snapshot;
        $dryRun = app(ReportPdfRefreshService::class)->refresh($chart, true);
        $this->assertNull($dryRun['backup']);
        $result = app(ReportPdfRefreshService::class)->refresh($chart);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($result['path']));
        $this->assertSame('%PDF-synthetic', Storage::disk('local')->get($result['backup']));
        $this->assertSame($snapshot, $chart->fresh()->snapshot);
        Http::assertNothingSent();
        $this->actingAs(User::factory()->create())->get(route('charts.report.download', $chart))->assertOk();
    }

    public function test_refresh_cannot_convert_legacy_missing_interpretations(): void
    {
        Storage::fake('local');
        $chart = $this->chart();
        $chart->update(['phase_one_pdf' => 'pdfs/legacy.pdf']);
        Storage::disk('local')->put('pdfs/legacy.pdf', '%PDF-synthetic');
        $this->expectExceptionMessage('Faltan secciones');
        app(ReportPdfRefreshService::class)->refresh($chart, true);
    }
}
