<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReportPdfRefreshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\InteriorMapFixture;
use Tests\TestCase;

final class ReportPdfRefreshTest extends TestCase
{
    use InteriorMapFixture, RefreshDatabase;

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
