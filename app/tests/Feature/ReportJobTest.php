<?php

namespace Tests\Feature;

use App\Exceptions\AiGenerationException;
use App\Jobs\ProcessReportStep;
use App\Models\BirthData;
use App\Models\Chart;
use App\Models\Person;
use App\Models\Place;
use App\Models\User;
use App\Services\ReportJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Unit\SunGenerationPipelineTest;

class ReportJobTest extends TestCase
{
    use RefreshDatabase;

    private function chart(): Chart
    {
        $person = Person::create(['alias' => 'Regression', 'full_name' => 'Regression Reader']);
        $place = Place::create(['city' => 'Madrid', 'country' => 'ES', 'latitude' => 40.4, 'longitude' => -3.7, 'timezone_identifier' => 'Europe/Madrid']);
        $birth = BirthData::create(['person_id' => $person->id, 'place_id' => $place->id, 'local_date' => '1990-01-01', 'local_time' => '12:00:00', 'timezone_identifier' => 'Europe/Madrid', 'utc_offset' => '+01:00', 'utc_datetime' => '1990-01-01 11:00:00']);
        $snapshot = ['houses' => []];
        foreach (range(1, 12) as $house) {
            $snapshot['houses'][$house] = ['longitude' => ($house - 1) * 30, 'sign' => 'aries', 'degrees' => 0, 'minutes' => 0, 'seconds' => 0];
        }
        foreach (['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto', 'ascendant', 'descendant'] as $point) {
            $snapshot[$point] = ['longitude' => 185, 'sign' => 'libra', 'degrees' => 5, 'minutes' => 0, 'seconds' => 0];
        }
        $chart = Chart::create(['person_id' => $person->id, 'birth_data_id' => $birth->id, 'configuration' => [], 'snapshot' => $snapshot, 'engine_version' => 'fixture', 'status' => 'calculated']);
        $chart->update(['natal_wheel_image' => 'data:image/jpeg;base64,'.base64_encode(file_get_contents(__DIR__.'/../Fixtures/wheel.jpg'))]);

        return $chart;

    }

    public function test_http_only_enqueues_and_deduplicates_and_restricts_job_access(): void
    {
        Http::preventStrayRequests();
        $chart = $this->chart();
        $user = User::factory()->create();
        $this->actingAs($user);
        $url = route('reports.jobs.store', $chart);
        $first = $this->postJson($url)->assertStatus(202)->assertJsonPath('status', 'queued');
        $this->postJson($url)->assertStatus(202)->assertJsonPath('job_id', $first->json('job_id'));
        $this->assertDatabaseCount('report_jobs', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('interpretations', 0);
        Http::assertNothingSent();
        $this->deleteJson(route('charts.destroy', $chart))->assertStatus(409);
        $this->getJson($first->json('status_url'))->assertOk();
        $this->getJson(route('reports.jobs.index'))->assertOk()->assertJsonCount(1);
        $this->get('/up')->assertOk();
        $this->actingAs(User::factory()->create())->getJson($first->json('status_url'))->assertForbidden();
        $this->postJson($url)->assertStatus(409);
    }

    public function test_worker_completes_report_and_pdf_from_persisted_steps_without_http_session(): void
    {
        config(['ai.enabled' => true, 'ai.api_key' => 'test-key']);
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $this->assertSame(1, DB::transactionLevel(), 'Only the test isolation transaction may be open during OpenAI');
            $input = json_decode($request['messages'][1]['content'], true);

            return Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(SunGenerationPipelineTest::sample($input['stage'], $input['door']))]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30]]);
        });
        $chart = $this->chart();
        $snapshot = $chart->snapshot;
        $job = app(ReportJobService::class)->start($chart, User::factory()->create()->id, ['sol', 'luna', 'ascendente', 'descendente']);
        auth()->logout();
        for ($i = 0; $i < 100; $i++) {
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
        $this->assertCount(4, $job->drafts);
        $this->assertSame(44, $chart->interpretations()->where('ai_assisted', true)->count());
        $this->assertSame($snapshot, $chart->fresh()->snapshot);
        Storage::disk('local')->assertExists($chart->fresh()->phase_one_pdf);
        Http::assertSentCount(88);
        (new ProcessReportStep($job->id, 1))->handle(app(ReportJobService::class));
        Http::assertSentCount(88);
    }

    public function test_failed_stage_keeps_draft_and_retry_resumes_only_pending_stage(): void
    {
        config(['ai.enabled' => true, 'ai.api_key' => 'test-key']);
        $rateLimited = true;
        Http::fake(function ($request) use (&$rateLimited) {
            $input = json_decode($request['messages'][1]['content'], true);
            if ($input['stage'] === 'sign' && $rateLimited) {
                return Http::response(['error' => ['message' => 'Rate limited']], 429);
            }

            return Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(SunGenerationPipelineTest::sample($input['stage'], $input['door']))]]]]);
        });
        $job = app(ReportJobService::class)->start($this->chart(), User::factory()->create()->id, ['sol']);
        $service = app(ReportJobService::class);
        $service->process($job);
        $service->process($job);
        $saved = $job->drafts;
        $step = new ProcessReportStep($job->id, $job->cursor);
        try {
            $step->handle($service);
            $this->fail('Expected rate limit');
        } catch (AiGenerationException $error) {
            $step->failed($error);
        }
        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertSame($saved, $job->drafts);
        $cursor = $job->cursor;
        $service->retry($job);
        $this->assertSame($cursor, $job->cursor);
        $this->assertSame($saved, $job->drafts);
        $this->assertSame([60, 180, 540], $step->backoff());
        $rateLimited = false;
        $step->handle($service);
        $job->refresh();
        $this->assertSame($cursor + 1, $job->cursor);
        $this->assertSame($saved['sol']['completed']['function'], $job->drafts['sol']['completed']['function']);
        Http::assertSentCount(3);
    }
}
