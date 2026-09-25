<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\ReportJob;
use App\Models\User;
use App\Services\ReportMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $status = 'completed'): ReportJob
    {
        $person = \App\Models\Person::create(['alias' => 'Report owner']);
        $place = \App\Models\Place::create(['city' => 'Madrid', 'country' => 'ES', 'latitude' => 40.4, 'longitude' => -3.7, 'timezone_identifier' => 'Europe/Madrid']);
        $birth = \App\Models\BirthData::create(['person_id' => $person->id, 'place_id' => $place->id, 'local_date' => '1990-01-01', 'local_time' => '12:00:00', 'timezone_identifier' => 'Europe/Madrid', 'utc_offset' => '+01:00', 'utc_datetime' => '1990-01-01 11:00:00']);
        $chart = \App\Models\Chart::create(['person_id' => $person->id, 'birth_data_id' => $birth->id, 'configuration' => [], 'snapshot' => [], 'engine_version' => 'fixture', 'status' => 'calculated']);

        return ReportJob::create(['chart_id' => $chart->id, 'user_id' => User::factory()->create()->id, 'status' => $status, 'doors' => ['sol']]);
    }

    public function test_dismissal_persists_without_deleting_chart_job_or_pdf(): void
    {
        Storage::fake('local');
        $job = $this->job();
        $job->chart->update(['phase_one_pdf' => 'pdfs/retained.pdf']);
        Storage::disk('local')->put('pdfs/retained.pdf', 'pdf fixture');

        $this->actingAs(User::findOrFail($job->user_id))->postJson(route('reports.jobs.dismiss', $job))
            ->assertOk()->assertJsonPath('dismissed', true);

        $this->assertNotNull($job->fresh()->dismissed_at);
        $this->assertModelExists($job);
        $this->assertModelExists($job->chart);
        Storage::disk('local')->assertExists('pdfs/retained.pdf');
        $this->getJson(route('reports.jobs.index'))->assertJsonCount(0);
        $this->getJson(route('reports.jobs.show', $job))->assertJsonPath('status', 'completed');
    }

    public function test_cannot_dismiss_another_users_notice_or_a_running_job(): void
    {
        $job = $this->job('queued');
        $this->actingAs(User::factory()->create())->postJson(route('reports.jobs.dismiss', $job))->assertForbidden();
        $this->actingAs(User::findOrFail($job->user_id))->postJson(route('reports.jobs.dismiss', $job))->assertConflict();
        $this->assertNull($job->fresh()->dismissed_at);
    }

    public function test_guest_cannot_dismiss_a_notice(): void
    {
        $job = $this->job();
        $this->postJson(route('reports.jobs.dismiss', $job))->assertUnauthorized();
        $this->assertNull($job->fresh()->dismissed_at);
    }

    public function test_failed_notice_can_be_dismissed_and_retry_restores_it(): void
    {
        $job = $this->job('failed');
        $this->actingAs(User::findOrFail($job->user_id))->postJson(route('reports.jobs.dismiss', $job))->assertOk();
        $this->getJson(route('reports.jobs.index'))->assertJsonCount(0);
        $this->postJson(route('reports.jobs.retry', $job))->assertOk()->assertJsonPath('status', 'queued');
        $this->getJson(route('reports.jobs.index'))->assertJsonCount(1);
        $this->assertNull($job->fresh()->dismissed_at);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_old_undismissed_notices_remain_visible(): void
    {
        $job = $this->job();
        $job->update(['updated_at' => now()->subDays(5)]);
        $this->actingAs(User::findOrFail($job->user_id))->getJson(route('reports.jobs.index'))->assertJsonCount(1);
    }

    public function test_compression_preserves_content_and_reads_legacy_drafts(): void
    {
        $job = $this->job();
        $draft = ['sol' => ['completed' => ['text' => str_repeat('Contenido íntegro. ', 500)], 'next' => 4]];
        DB::table('report_jobs')->where('id', $job->id)->update(['drafts' => json_encode($draft)]);
        $this->assertSame($draft, $job->fresh()->drafts);

        $job->update(['drafts' => $draft]);

        $this->assertSame($draft, $job->fresh()->drafts);
        $this->assertLessThan(strlen(json_encode($draft)), strlen($job->fresh()->getRawOriginal('drafts')));
        config(['reports.compress_drafts' => false]);
        $job->update(['drafts' => $draft]);
        $this->assertSame($draft, json_decode($job->fresh()->getRawOriginal('drafts'), true));
    }

    public function test_metrics_keep_attempts_and_safe_error_codes(): void
    {
        $job = $this->job();
        $metrics = app(ReportMetrics::class);
        $first = $metrics->begin($job->id, 'ai', 'sol.function', 'fixture-model');
        $metrics->finish($first, hrtime(true), new \RuntimeException('secret must not be stored'));
        $second = $metrics->begin($job->id, 'ai', 'sol.function', 'fixture-model');
        $metrics->finish($second, hrtime(true), null, ['input_tokens' => 100, 'output_tokens' => 200]);

        $this->assertDatabaseHas('report_metrics', ['id' => $first, 'outcome' => 'error', 'error_code' => 'PROCESSING_ERROR']);
        $this->assertDatabaseHas('report_metrics', ['id' => $second, 'attempt' => 2, 'outcome' => 'success', 'input_tokens' => 100]);
        $this->assertStringNotContainsString('secret must not be stored', json_encode(DB::table('report_metrics')->get()));
        $this->artisan('reports:timings', ['job' => $job->id])->assertSuccessful();
    }

    public function test_seeding_preserves_existing_password_hash(): void
    {
        $user = User::factory()->create(['email' => 'dhara', 'password' => 'Existing-test-password']);
        $hash = $user->password;
        $this->seed();
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertTrue(Hash::check('Existing-test-password', $hash));
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_password_command_stores_hash_and_login_regenerates_session(): void
    {
        $user = User::factory()->create();
        $this->artisan('users:password', ['login' => $user->email])
            ->expectsQuestion('Nueva contraseña (mínimo 12 caracteres)', 'Changed-test-password')
            ->expectsQuestion('Repite la contraseña', 'Changed-test-password')->assertSuccessful();
        $this->assertTrue(Hash::check('Changed-test-password', $user->fresh()->password));
        $this->withSession(['probe' => true]);
        $oldId = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'Changed-test-password'])->assertRedirect(route('charts.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, session()->getId());
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('probe'));
    }

    public function test_incorrect_password_fails_and_login_is_rate_limited(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        }
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertTooManyRequests();
    }
}
