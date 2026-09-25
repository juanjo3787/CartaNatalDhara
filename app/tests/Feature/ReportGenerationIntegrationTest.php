<?php

namespace Tests\Feature;

use App\Models\BirthData;
use App\Models\Chart;
use App\Models\Person;
use App\Models\Place;
use App\Models\User;
use App\Services\Doors\AbstractDoorPipeline;
use App\Services\PhaseOneAiGenerationService;
use App\Services\PhaseOneManualSaveService;
use App\Services\PhaseOneReportService;
use App\Services\ReportState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Unit\SunGenerationPipelineTest;

final class ReportGenerationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_job_persists_once_and_web_and_pdf_share_all_twelve_states(): void
    {
        config(['ai.enabled' => true, 'ai.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $input = json_decode($request['messages'][1]['content'], true);

            return Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(SunGenerationPipelineTest::sample($input['stage'], $input['door']))]]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30]]);
        });
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
        $service = app(PhaseOneAiGenerationService::class);
        foreach (['sol', 'luna', 'ascendente', 'descendente'] as $door) {
            $session = 'fresh-'.$door;
            Cache::put('phase1_ai_draft_'.$door.'_'.$chart->id.'_'.hash('sha256', $session), ['next' => 100, 'completed' => ['legacy']]);
            foreach (AbstractDoorPipeline::STAGES as $stage) {
                $result = $service->generateDoorStage($chart, $door, $stage, $session);
            }
            $this->assertTrue($result['complete']);
            $this->assertSame(11, $result['blocks']);
        }
        $before = $chart->interpretations()->where('door', 'descendente')->orderBy('block')->pluck('content', 'block')->all();
        foreach (AbstractDoorPipeline::STAGES as $stage) {
            $service->generateDoorStage($chart, 'descendente', $stage, 'repeat-descendente');
        }
        $this->assertSame($before, $chart->interpretations()->where('door', 'descendente')->orderBy('block')->pluck('content', 'block')->all());
        $this->assertSame(44, $chart->interpretations()->count());
        $this->assertSame($snapshot, $chart->fresh()->snapshot);
        $report = app(PhaseOneReportService::class)->build($chart);
        foreach ($report['doors'] as $door) {
            $this->assertCount(3, $door['states']);
        }
        $web = view('reports.phase-one.template', compact('chart', 'report') + ['pdf' => false])->render();
        $pdf = view('reports.phase-one.template', compact('chart', 'report') + ['pdf' => true])->render();
        $results = [];
        foreach ([$web, $pdf] as $html) {
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);
            $states = [];
            foreach ($xpath->query('//*[@data-section-id]') as $block) {
                $id = $block->getAttribute('data-section-id');
                $stateName = explode('.', $id)[1];
                if (! isset(ReportState::HEADINGS[$stateName])) {
                    continue;
                }
                $this->assertArrayNotHasKey($id, $states);
                $states[$id] = $block->textContent;
                $body = $xpath->query('./div[@class="report-block-body"]', $block)->item(0);
                $children = $xpath->query('./*', $body);
                $this->assertSame('p', $children->item(0)->nodeName);
                $this->assertSame(3, $xpath->query('./h3', $body)->length);
                $this->assertSame(21, $xpath->query('./ol/li', $body)->length);
                foreach ($xpath->query('./h3', $body) as $heading) {
                    $this->assertSame('ol', $xpath->query('following-sibling::*[1]', $heading)->item(0)->nodeName);
                }
            }
            $this->assertCount(12, $states);
            $results[] = $states;
        }
        $this->assertSame($results[0], $results[1]);
        Http::assertSentCount(count(AbstractDoorPipeline::STAGES) * 5);
        $editable = app(PhaseOneReportService::class)->editableContent($chart);
        $chart->forceFill(['phase_one_pdf' => 'old.pdf'])->save();
        app(PhaseOneManualSaveService::class)->save($chart, $editable['shared'], $editable['doors']);
        $this->assertNull($chart->fresh()->phase_one_pdf);
        foreach ($chart->interpretations()->whereIn('block', array_keys(ReportState::HEADINGS))->get() as $row) {
            $this->assertSame(3, substr_count($row->content, '<h3>'));
            ReportState::fromRendered([$row->content], $row->door.'.'.$row->block, $row->block);
        }
        $broken = $chart->interpretations()->where('door', 'descendente')->where('block', 'excess')->firstOrFail();
        $broken->update(['ai_assisted' => true, 'content' => '<h3>Características que puedes observar</h3><h3>Pautas</h3>']);
        $this->withoutVite()->actingAs(User::factory()->create());
        $this->get(route('charts.report', $chart))->assertStatus(422)->assertSee('Regenerar esta puerta');
        $this->getJson(route('charts.report', $chart))->assertStatus(422)->assertJsonPath('error_code', 'SECTION_SCHEMA_CONTAMINATION');
        $this->assertSame($broken->content, $broken->fresh()->content);
        $sunBefore = $chart->interpretations()->where('door', 'sol')->orderBy('id')->get()->toArray();
        foreach (['luna', 'ascendente'] as $door) {
            $chart->interpretations()->where('door', $door)->whereIn('block', ['harmony', 'excess'])
                ->update(['ai_assisted' => true, 'content' => '<ol><li>Contenido antiguo</li></ol>']);
        }
        $pendingDoors = ['luna', 'ascendente', 'descendente'];
        $response = $this->get(route('charts.report', $chart))->assertStatus(422)
            ->assertViewHas('doors', $pendingDoors)->assertSee('Regenerar los apartados pendientes');
        $this->assertStringContainsString("data-doors='".json_encode($pendingDoors)."'", $response->getContent());
        $this->getJson(route('charts.report', $chart))->assertStatus(422)->assertJsonPath('invalid_doors', $pendingDoors);
        foreach ($pendingDoors as $door) {
            foreach (AbstractDoorPipeline::STAGES as $stage) {
                $result = $service->generateDoorStage($chart, $door, $stage, 'repair-'.$door);
            }
            $this->assertTrue($result['complete']);
        }
        $this->get(route('charts.report', $chart))->assertOk();
        $this->assertSame($sunBefore, $chart->interpretations()->where('door', 'sol')->orderBy('id')->get()->toArray());
        $this->assertSame($snapshot, $chart->fresh()->snapshot);
    }
}
