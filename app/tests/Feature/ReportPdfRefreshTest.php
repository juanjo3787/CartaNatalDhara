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

    public function test_reference_word_content_keeps_a_comparable_pdf_density(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $chart = $this->chart();
        $chapters = $this->referenceChapters();
        $snapshot = self::snapshot();
        $signs = array_keys(InteriorMapMaster::SIGNS);
        foreach (range(1, 12) as $house) {
            $longitude = fmod(35 + ($house - 1) * 30, 360);
            $snapshot['houses'][$house] = ['longitude' => $longitude, 'sign' => $signs[(int) floor($longitude / 30)]];
        }
        foreach (['sun' => 190, 'moon' => 135, 'ascendant' => 35, 'descendant' => 215] as $point => $longitude) {
            $snapshot[$point] = ['longitude' => $longitude, 'sign' => $signs[(int) floor($longitude / 30)]];
        }
        $chart->update(['snapshot' => $snapshot]);
        $this->assertCount(5, $chapters);
        foreach ($chapters as $chapter => $content) {
            $expected = $chapter === 'integration' ? InteriorMapMaster::INTEGRATION_TITLES : InteriorMapMaster::TITLES;
            $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($content));
            if ($chapter !== 'integration') {
                foreach (['harmony', 'deficit', 'excess'] as $state) {
                    $this->assertCount(5, $content[$state]);
                }
            }
            app(PhaseOneReportService::class)->storeChapter($chart, $chapter === 'integration' ? null : $chapter, $content);
        }
        Log::spy();
        app(ReportPdfService::class)->store($chart, 102);
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            if ($message !== 'Phase 1 PDF rendered.') {
                return false;
            }
            $this->assertLessThanOrEqual(18, $context['pages'], 'The reference content must not grow into a fragmented dossier.');
            $this->assertSame(['sol', 'luna', 'ascendente', 'descendente', 'integration'], array_keys($context['index_pages']));

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
