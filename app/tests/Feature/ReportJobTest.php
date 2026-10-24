<?php

namespace Tests\Feature;

use App\Exceptions\AiGenerationException;
use App\Exceptions\ReportContentValidationException;
use App\Jobs\ProcessReportStep;
use App\Models\ReportJob;
use App\Models\User;
use App\Services\InteriorMapMaster;
use App\Services\PhaseOneAiGenerationService;
use App\Services\PhaseOneReportService;
use App\Services\ReportJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\InteriorMapFixture;
use Tests\TestCase;

final class ReportJobTest extends TestCase
{
    use InteriorMapFixture, RefreshDatabase;

    public function test_http_enqueues_deduplicates_and_restricts_access_without_calling_ai(): void
    {
        Http::preventStrayRequests();
        $chart = $this->chart();
        $this->actingAs(User::factory()->create());
        $url = route('reports.jobs.store', $chart);
        $first = $this->postJson($url, ['doors' => ['sol']])->assertAccepted();
        $this->postJson($url)->assertAccepted()->assertJsonPath('job_id', $first->json('job_id'));
        $job = ReportJob::findOrFail($first->json('job_id'));
        $this->assertSame(InteriorMapMaster::DOORS, $job->doors);
        $this->assertDatabaseCount('report_jobs', 1);
        $this->assertDatabaseCount('jobs', 1);
        Http::assertNothingSent();
        $this->deleteJson(route('charts.destroy', $chart))->assertConflict();
        $this->actingAs(User::factory()->create())->getJson($first->json('status_url'))->assertForbidden();
    }

    public function test_worker_generates_five_chapters_and_a_real_pdf_without_historical_input(): void
    {
        config(['ai.enabled' => true, 'ai.api_key' => 'test-key']);
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $input = json_decode($request['messages'][1]['content'], true);
            $this->assertArrayNotHasKey('person', $input);
            $this->assertSame('moon', $input['ASTROLOGICAL_FACTS']['sol']['rulers']['traditional'][0]['planet']);
            $this->assertSame('Sagitario', $input['ASTROLOGICAL_FACTS']['sol']['rulers']['traditional'][0]['sign']);
            $this->assertStringContainsString('necesidades y respuestas emocionales', $request['messages'][0]['content']);
            $this->assertStringContainsString('tradicionales prioritarias', $request['messages'][0]['content']);
            $this->assertStringNotContainsString('Pastora', $request['messages'][0]['content'].$request['messages'][1]['content']);
            if ($input['chapter'] === 'audit') {
                $this->assertSame(InteriorMapMaster::CHAPTERS, array_keys($input['analysis_coverage']));
                $this->assertSame(InteriorMapMaster::CHAPTERS, array_keys($input['current_report']));
                $corrected = self::chapter('sol');
                $corrected['concepts'][0]['characteristics'][0]['paragraphs'][] = 'Corrección específica de la auditoría conjunta.';
                $content = ['replacements' => [['chapter' => 'sol', 'content' => $corrected]]];
                $this->assertSame(16000, $request['max_completion_tokens']);
            } else {
                $this->assertSame([$input['chapter']], array_keys($input['analysis_coverage']));
                $this->assertCount(8, $input['analysis_coverage'][$input['chapter']]);
                $index = array_search($input['chapter'], InteriorMapMaster::CHAPTERS, true);
                $this->assertSame(array_slice(InteriorMapMaster::CHAPTERS, 0, $index), array_keys($input['current_report']));
                $this->assertSame(3200, $input['editorial_target']['word_target']);
                $this->assertSame(6000, $input['editorial_target']['maximum_words']);
                $this->assertArrayNotHasKey('section_word_targets', $input);
                $content = self::chapter($input['chapter']);
            }

            return Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($content)]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20]]);
        });
        $chart = $this->chart();
        $snapshot = $chart->snapshot;
        $job = app(ReportJobService::class)->start($chart, User::factory()->create()->id, InteriorMapMaster::DOORS);
        for ($i = 0; $i < 12; $i++) {
            $queued = Queue::connection('reports')->pop('reports');
            if (! $queued) {
                break;
            }
            $queued->fire();
            $queued->delete();
        }
        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame(100, $job->progress);
        $this->assertNull($job->active_chart_id);
        $this->assertFalse($chart->fresh()->report_is_stale);
        $this->assertSame($snapshot, $chart->fresh()->snapshot);
        $this->assertDatabaseCount('interpretations', 5);
        $this->assertTrue($job->drafts['_audited']);
        $this->assertStringContainsString('Corrección específica', app(PhaseOneReportService::class)->editableContent($chart->fresh())['doors']['sol']['concepts']);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($chart->fresh()->phase_one_pdf));
        $opposites = $chart->reportGenerations()->where('filename', 'natal-universal-opposites')->firstOrFail();
        $this->actingAs(User::findOrFail($job->user_id))->get(route('charts.report.history', $chart))
            ->assertOk()->assertSee('Los opuestos complementarios')->assertSee('Auditoría conjunta');
        $this->get(route('charts.report.prompts', [$chart, $opposites]))
            ->assertOk()->assertSee('Los opuestos complementarios')->assertSee('único prompt maestro');
        Http::assertSentCount(6);
        (new ProcessReportStep($job->id, 1))->handle(app(ReportJobService::class));
        Http::assertSentCount(6);
    }

    public function test_obsolete_jobs_cannot_be_retried(): void
    {
        $job = ReportJob::create(['chart_id' => $this->chart()->id, 'user_id' => User::factory()->create()->id, 'doors' => ['sol'], 'status' => 'failed']);
        $this->actingAs(User::findOrFail($job->user_id))->postJson(route('reports.jobs.retry', $job))->assertConflict();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_changed_birth_data_require_full_regeneration_not_pdf_only(): void
    {
        $chart = $this->chart();
        $chart->update(['report_is_stale' => true]);
        $this->actingAs(User::factory()->create())->postJson(route('charts.report.validate', $chart))->assertConflict();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_failed_job_cannot_resume_after_the_chart_changes(): void
    {
        $chart = $this->chart();
        $user = User::factory()->create();
        $job = app(ReportJobService::class)->start($chart, $user->id, InteriorMapMaster::DOORS);
        $job->update(['status' => 'failed', 'active_chart_id' => null]);
        $chart->person->update(['full_name' => 'New synthetic name']);
        $this->actingAs($user)->postJson(route('reports.jobs.retry', $job))->assertConflict();
        $this->assertSame('failed', $job->fresh()->status);
    }

    public function test_oversized_chapter_retry_receives_feedback_and_preserves_word_limits(): void
    {
        config(['ai.enabled' => true, 'ai.api_key' => 'test-key']);
        Http::preventStrayRequests();
        $calls = 0;
        Http::fake(function ($request) use (&$calls) {
            $input = json_decode($request['messages'][1]['content'], true);
            $this->assertSame(3200, $input['editorial_target']['word_target']);
            $content = self::chapter('sol');
            if (++$calls === 1) {
                $this->assertArrayNotHasKey('previous_validation_error', $input);
                $this->assertArrayNotHasKey('revision_candidate', $input);
                $content['concepts'][0]['characteristics'][0]['paragraphs'][] = str_repeat('palabra ', 6000);
            } else {
                $this->assertStringContainsString('Extensión de bloque inválida', $input['previous_validation_error']);
                $this->assertStringContainsString('6000', $input['previous_validation_error']);
                $this->assertArrayHasKey('retry_instruction', $input);
                $expected = self::chapter('sol');
                $expected['concepts'][0]['characteristics'][0]['paragraphs'][] = str_repeat('palabra ', 6000);
                $this->assertSame($expected, $input['revision_candidate']);
                $master = new InteriorMapMaster;
                $this->assertSame($master->wordCount($expected), $input['measured_word_count']);
                $this->assertStringContainsString('sin añadir relleno', $input['retry_instruction']);
            }

            return Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode($content)]]]]);
        });
        $chart = $this->chart();
        $service = app(ReportJobService::class);
        $job = $service->start($chart, User::factory()->create()->id, InteriorMapMaster::DOORS);
        $service->process($job);
        $step = new ProcessReportStep($job->id, 1);
        try {
            $step->handle($service);
            $this->fail('The oversized chapter must not be accepted.');
        } catch (ReportContentValidationException $exception) {
            $this->assertStringContainsString('Extensión de bloque inválida', $exception->getMessage());
        }
        $job->refresh();
        $this->assertSame(10, $job->progress);
        $this->assertSame('CONTENT_VALIDATION_FAILED', $job->error_code);
        $this->assertArrayHasKey('sol', $job->drafts['_validation_feedback']);
        $this->assertArrayHasKey('sol', $job->drafts['_validation_candidate']);
        $this->assertDatabaseCount('interpretations', 0);
        $this->actingAs(User::findOrFail($job->user_id))->getJson(route('reports.jobs.show', $job))
            ->assertOk()->assertJsonPath('error_message', $job->error_message);
        $step->handle($service);
        $job->refresh();
        $this->assertSame(20, $job->progress);
        $this->assertSame(2, $job->cursor);
        $this->assertNull($job->error_message);
        $this->assertArrayNotHasKey('sol', $job->drafts['_validation_feedback']);
        $this->assertArrayNotHasKey('sol', $job->drafts['_validation_candidate']);
        $this->assertArrayHasKey('sol', $job->drafts);
        $this->assertDatabaseCount('interpretations', count(InteriorMapMaster::TITLES));
        Http::assertSentCount(2);
    }

    public function test_exhausted_content_validation_attempts_expose_the_actual_reason(): void
    {
        $job = app(ReportJobService::class)->start($this->chart(), User::factory()->create()->id, InteriorMapMaster::DOORS);
        app(ReportJobService::class)->process($job);
        (new ProcessReportStep($job->id, 1))->failed(new ReportContentValidationException('Extensión de bloque inválida: 6100 palabras.'));
        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertNull($job->active_chart_id);
        $this->assertSame('CONTENT_VALIDATION_FAILED', $job->error_code);
        $this->assertStringContainsString('6100 palabras', $job->error_message);
        $this->assertDatabaseCount('interpretations', 0);
    }

    public function test_missing_previous_block_stops_generation_without_a_provider_call(): void
    {
        config(['ai.enabled' => true]);
        Http::preventStrayRequests();
        $chart = $this->chart();
        $job = app(ReportJobService::class)->start($chart, User::factory()->create()->id, InteriorMapMaster::DOORS);
        try {
            app(PhaseOneAiGenerationService::class)->generateChapter($chart, 'luna', $job);
            $this->fail('Missing sequential context must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Falta el bloque anterior sol', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_permanent_provider_failure_stops_the_worker_and_exposes_the_reason(): void
    {
        config(['ai.enabled' => true, 'ai.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['error' => ['type' => 'insufficient_quota']], 429)]);
        $service = app(ReportJobService::class);
        $job = $service->start($this->chart(), User::factory()->create()->id, InteriorMapMaster::DOORS);
        $service->process($job);
        $step = (new ProcessReportStep($job->id, 1))->withFakeQueueInteractions();
        $step->handle($service);
        $step->assertFailedWith(AiGenerationException::class);
        $this->assertSame('failed', $job->fresh()->status);
        $this->assertSame(AiGenerationException::QUOTA_EXCEEDED, $job->fresh()->error_code);
        $this->assertStringContainsString('cuota', $job->fresh()->error_message);
        $this->assertNull($job->fresh()->active_chart_id);
        $this->assertDatabaseCount('interpretations', 0);
        Http::assertSentCount(1);
    }

    public function test_temporary_provider_failure_remains_retryable_without_advancing_the_report(): void
    {
        config(['ai.enabled' => true, 'ai.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 503)]);
        $service = app(ReportJobService::class);
        $job = $service->start($this->chart(), User::factory()->create()->id, InteriorMapMaster::DOORS);
        $service->process($job);
        $step = (new ProcessReportStep($job->id, 1))->withFakeQueueInteractions();
        try {
            $step->handle($service);
            $this->fail('A temporary error must reach the queue retry handler.');
        } catch (AiGenerationException $exception) {
            $this->assertTrue($exception->isRetryable());
        }
        $step->assertNotFailed();
        $this->assertSame(1, $job->fresh()->cursor);
        $this->assertNotNull($job->fresh()->active_chart_id);
        $this->assertSame(AiGenerationException::CONNECTION_ERROR, $job->fresh()->error_code);
        $this->assertDatabaseCount('interpretations', 0);
        Http::assertSentCount(1);
    }

    public function test_invalid_audit_does_not_publish_partial_corrections_or_mark_report_audited(): void
    {
        config(['ai.enabled' => true, 'ai.api_key' => 'test-key']);
        Http::preventStrayRequests();
        $chart = $this->chart();
        $this->saveChapters($chart);
        $drafts = [];
        foreach (InteriorMapMaster::CHAPTERS as $chapter) {
            $drafts[$chapter] = self::chapter($chapter);
        }
        $job = ReportJob::create(['chart_id' => $chart->id, 'user_id' => User::factory()->create()->id, 'doors' => InteriorMapMaster::DOORS, 'status' => 'auditing', 'drafts' => $drafts]);
        $candidate = self::chapter('sol');
        $candidate['concepts'][0]['characteristics'][0]['paragraphs'][] = 'Tu Sol en Libra.';
        Http::fake(['*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(['replacements' => [['chapter' => 'sol', 'content' => $candidate]]])]]]])]);
        $previous = app(PhaseOneReportService::class)->editableContent($chart);
        try {
            app(PhaseOneAiGenerationService::class)->auditReport($chart, $job);
            $this->fail('An invalid audit must be rejected.');
        } catch (ReportContentValidationException $exception) {
            $this->assertStringContainsString('posición canónica', $exception->getMessage());
        }
        $this->assertArrayNotHasKey('_audited', $job->fresh()->drafts);
        $this->assertSame($drafts['sol'], $job->fresh()->drafts['sol']);
        $this->assertSame($previous, app(PhaseOneReportService::class)->editableContent($chart->fresh()));
    }
}
