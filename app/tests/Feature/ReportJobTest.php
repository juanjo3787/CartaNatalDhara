<?php

namespace Tests\Feature;

use App\Exceptions\ReportContentValidationException;
use App\Jobs\ProcessReportStep;
use App\Models\ReportJob;
use App\Models\User;
use App\Services\InteriorMapMaster;
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
            $this->assertStringNotContainsString('Pastora', $request['messages'][0]['content'].$request['messages'][1]['content']);
            if ($input['chapter'] === 'integration') {
                $this->assertSame(InteriorMapMaster::DOORS, array_keys($input['current_report']));
            } else {
                $this->assertArrayNotHasKey('current_report', $input);
            }

            return Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(self::chapter($input['chapter']))]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20]]);
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
        $this->assertDatabaseCount('interpretations', 4 * count(InteriorMapMaster::TITLES) + count(InteriorMapMaster::INTEGRATION_TITLES));
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($chart->fresh()->phase_one_pdf));
        $integration = $chart->reportGenerations()->where('ai_assisted', true)->whereNull('door')->firstOrFail();
        $this->actingAs(User::findOrFail($job->user_id))->get(route('charts.report.history', $chart))
            ->assertOk()->assertSee('Integración');
        $this->get(route('charts.report.prompts', [$chart, $integration]))
            ->assertOk()->assertSee('Integración')->assertSee('único prompt maestro');
        Http::assertSentCount(5);
        (new ProcessReportStep($job->id, 1))->handle(app(ReportJobService::class));
        Http::assertSentCount(5);
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
            $this->assertSame(array_keys(InteriorMapMaster::TITLES), array_keys($input['section_word_targets']));
            $this->assertSame(915, array_sum($input['section_word_targets']));
            $content = self::chapter('sol');
            if (++$calls === 1) {
                $this->assertArrayNotHasKey('previous_validation_error', $input);
                $content['practice'][] = str_repeat('palabra ', 400);
            } else {
                $this->assertStringContainsString('Extensión de capítulo inválida', $input['previous_validation_error']);
                $this->assertStringContainsString('1050', $input['previous_validation_error']);
                $this->assertArrayHasKey('retry_instruction', $input);
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
            $this->assertStringContainsString('Extensión de capítulo inválida', $exception->getMessage());
        }
        $job->refresh();
        $this->assertSame(11, $job->progress);
        $this->assertSame('CONTENT_VALIDATION_FAILED', $job->error_code);
        $this->assertArrayHasKey('sol', $job->drafts['_validation_feedback']);
        $this->assertDatabaseCount('interpretations', 0);
        $this->actingAs(User::findOrFail($job->user_id))->getJson(route('reports.jobs.show', $job))
            ->assertOk()->assertJsonPath('error_message', $job->error_message);
        $step->handle($service);
        $job->refresh();
        $this->assertSame(22, $job->progress);
        $this->assertSame(2, $job->cursor);
        $this->assertNull($job->error_message);
        $this->assertArrayNotHasKey('sol', $job->drafts['_validation_feedback']);
        $this->assertArrayHasKey('sol', $job->drafts);
        $this->assertDatabaseCount('interpretations', count(InteriorMapMaster::TITLES));
        Http::assertSentCount(2);
    }

    public function test_exhausted_content_validation_attempts_expose_the_actual_reason(): void
    {
        $job = app(ReportJobService::class)->start($this->chart(), User::factory()->create()->id, InteriorMapMaster::DOORS);
        app(ReportJobService::class)->process($job);
        (new ProcessReportStep($job->id, 1))->failed(new ReportContentValidationException('Extensión de capítulo inválida: 1228 palabras; se requieren 780–1050.'));
        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertNull($job->active_chart_id);
        $this->assertSame('CONTENT_VALIDATION_FAILED', $job->error_code);
        $this->assertStringContainsString('1228 palabras', $job->error_message);
        $this->assertDatabaseCount('interpretations', 0);
    }
}
