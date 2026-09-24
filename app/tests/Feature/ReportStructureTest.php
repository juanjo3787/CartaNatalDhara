<?php

namespace Tests\Feature;

use App\Services\Doors\AbstractDoorPipeline;
use App\Services\Doors\DoorPipelineFactory;
use App\Services\ReportState;
use App\Services\ReportStageMerger;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\SunGenerationPipelineTest;

final class ReportStructureTest extends TestCase
{
    public static function states(): array
    {
        $cases = [];
        foreach (['sol', 'luna', 'ascendente', 'descendente'] as $door) {
            foreach (['harmony', 'deficit', 'excess'] as $state) {
                $cases[$door.'.'.$state] = [$door, $state];
            }
        }
        return $cases;
    }

    #[DataProvider('states')]
    public function test_state_has_one_heading_per_list_and_survives_persistence(string $door, string $state): void
    {
        $content = [];
        foreach (AbstractDoorPipeline::STAGES as $stage) {
            $content = ReportStageMerger::merge($content, SunGenerationPipelineTest::sample($stage, $door));
        }
        $blocks = DoorPipelineFactory::for($door)->render($content)[$state];
        $persisted = preg_split('/\R{2,}/', implode("\n\n", $blocks));
        $model = ReportState::fromRendered($persisted, $door.'.'.$state, $state);
        $this->assertSame($content[$state]['development'], $model['development']);
        $this->assertSame($blocks, ReportState::render($model, $state));
        foreach (['characteristics', 'guidelines', 'examples'] as $key) {
            $this->assertCount(7, $model[$key]);
            $this->assertSame(range(1, 7), array_column($model[$key], 'id'));
        }
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.implode('', $blocks).'</div>');
        $headings = $dom->getElementsByTagName('h3');
        $this->assertCount(3, $headings);
        foreach ($headings as $index => $heading) {
            $this->assertSame(ReportState::HEADINGS[$state][$index], $heading->textContent);
            $this->assertSame('ol', $heading->nextSibling->nodeName);
            $this->assertCount(7, $heading->nextSibling->getElementsByTagName('li'));
        }
        $this->assertSame('p', $headings->item(0)->previousSibling->nodeName);
    }

    #[DataProvider('states')]
    public function test_contamination_and_premature_headings_are_rejected(string $door, string $state): void
    {
        $data = SunGenerationPipelineTest::sample($state.'_development', $door)[$state];
        $data['development'][0] .= ' '.ReportState::HEADINGS[$state][0];
        $this->expectExceptionMessage('SECTION_SCHEMA_CONTAMINATION');
        ReportState::assertNarrative($data['development'], $door.'.'.$state);
    }

    public function test_legacy_consecutive_empty_headings_are_rejected(): void
    {
        $this->expectExceptionMessage('SECTION_SCHEMA_CONTAMINATION');
        ReportState::fromRendered(array_map(fn ($h) => '<h3>'.$h.'</h3>', ReportState::HEADINGS['excess']), 'descendente.excess', 'excess');
    }

    public function test_regeneration_replaces_lists_and_retries_example_batches_by_id(): void
    {
        $old = ['excess' => ['development' => ['old one', 'old two', 'old three'], 'characteristics' => [['id' => 1, 'text' => 'old']]]];
        $new = ['excess' => ['development' => ['new'], 'characteristics' => [['id' => 1, 'text' => 'new']]]];
        $once = ReportStageMerger::merge($old, $new);
        $this->assertSame($new, $once);
        $this->assertSame($once, ReportStageMerger::merge($once, $new));
        $batchOne = SunGenerationPipelineTest::sample('excess_examples_1');
        $batchTwo = SunGenerationPipelineTest::sample('excess_examples_2');
        $full = ReportStageMerger::merge(ReportStageMerger::merge([], $batchOne), $batchTwo);
        $this->assertSame($full, ReportStageMerger::merge($full, $batchOne));
        $this->assertCount(7, $full['excess']['examples']);
    }
}
