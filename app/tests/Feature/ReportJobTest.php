<?php

namespace Tests\Feature;

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
}
