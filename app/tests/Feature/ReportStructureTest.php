<?php

namespace Tests\Feature;

use App\Services\Doors\AbstractDoorPipeline;
use App\Services\Doors\DoorPipelineFactory;
use App\Services\PdfPageGeometry;
use App\Services\ReportStageMerger;
use App\Services\ReportState;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Blade;
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
        $stale = $full;
        $stale['excess']['examples'][] = ['id' => 8, 'text' => 'legacy'];
        $this->assertSame($full, ReportStageMerger::merge($stale, $full));
    }

    public function test_dompdf_reserves_the_same_content_box_on_every_page(): void
    {
        $html = view('layouts.app', ['pdf' => true])->render();
        $template = file_get_contents(resource_path('views/reports/phase-one/template.blade.php'));
        $css = Blade::render(substr($template, strpos($template, '<style>'), strpos($template, '</style>') + 8 - strpos($template, '<style>')), ['pdf' => true]);
        $content = '<div class="report-document"><section class="report-page">'.str_repeat('<h2>Banda de sección</h2><p>'.str_repeat('Narrativa suficiente para provocar saltos automáticos. ', 50).'</p>', 8).'</section></div>';
        $dompdf = new Dompdf;
        $dompdf->loadHtml(str_replace('</body>', $css.$content.'</body>', $html));
        $boxes = [];
        $dompdf->setCallbacks([['event' => 'begin_page_reflow', 'f' => static function ($frame) use (&$boxes): void {
            $boxes[] = $frame->get_containing_block();
        }]]);
        $dompdf->render();
        $this->assertGreaterThan(2, count($boxes));
        foreach ($boxes as $box) {
            $this->assertEqualsWithDelta(PdfPageGeometry::MARGIN_PT, $box['y'], 0.01);
            $this->assertGreaterThan(PdfPageGeometry::HEADER_BOTTOM_PT + 10, $box['y']);
            $this->assertLessThan(841.89 - PdfPageGeometry::FOOTER_TOP_FROM_BOTTOM_PT - 10, $box['y'] + $box['h']);
        }
    }

    public function test_pdf_has_one_blank_page_after_cover_and_another_after_index(): void
    {
        $html = view('layouts.app', ['pdf' => true])->render();
        $template = file_get_contents(resource_path('views/reports/phase-one/template.blade.php'));
        $style = substr($template, strpos($template, '<style>'), strpos($template, '</style>') + 8 - strpos($template, '<style>'));
        $css = Blade::render($style, ['pdf' => true]);
        $content = '<div class="report-document">'
            .'<section class="report-page report-cover"></section>'
            .'<section class="report-page report-pdf-blank-page" aria-hidden="true"></section>'
            .'<section class="report-page"><h1>Índice</h1></section>'
            .'<section class="report-page report-pdf-blank-page" aria-hidden="true"></section>'
            .'<section class="report-page"><h1>Página 5</h1></section>'
            .'<section class="report-page"><h1>Página 6</h1></section>'
            .'<section class="report-page"><h1>Página 7</h1></section>'
            .'<section class="report-page"><h1>Página 8</h1></section>'
            .'<section class="report-page report-pdf-blank-page" aria-hidden="true" data-report-blank-page="9"></section>'
            .'<section class="report-page report-last-page" data-after-blank-page="true"><h1>Página 10</h1></section>'
            .'</div>';
        $dompdf = new Dompdf;
        $dompdf->loadHtml(str_replace('</body>', $css.$content.'</body>', $html));
        $blankPages = [];
        $followingPage = null;
        $dompdf->setCallbacks([['event' => 'begin_frame', 'f' => static function ($frame, $canvas) use (&$blankPages, &$followingPage): void {
            $node = $frame->get_node();
            if ($node instanceof \DOMElement && $node->hasAttribute('data-report-blank-page')) {
                $blankPages[] = $canvas->get_page_number();
            }
            if ($node instanceof \DOMElement && $node->hasAttribute('data-after-blank-page')) {
                $followingPage = $canvas->get_page_number();
            }
        }]]);
        $dompdf->render();

        $this->assertSame(10, $dompdf->getCanvas()->get_page_count());
        $this->assertSame([9], array_values(array_unique($blankPages)));
        $this->assertSame(10, $followingPage);
        $this->assertStringContainsString('height: 260mm;', $css);
        $this->assertStringContainsString('page-break-after: always;', $css);
    }
}
