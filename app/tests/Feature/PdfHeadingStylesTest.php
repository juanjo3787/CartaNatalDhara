<?php

namespace Tests\Feature;

use App\Models\BirthData;
use App\Models\Chart;
use App\Models\Place;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PdfHeadingStylesTest extends TestCase
{
    public function test_print_headings_match_the_exact_pre_responsive_styles_and_geometry(): void
    {
        $template = file_get_contents(resource_path('views/reports/phase-one/template.blade.php'));
        $style = substr($template, strpos($template, '<style>'), strpos($template, '</style>') + 8 - strpos($template, '<style>'));
        $reference = file_get_contents(__DIR__.'/../Fixtures/pdf-headings-before-responsive.css');
        $expected = $this->renderHeadings(Blade::render($reference, ['pdf' => true]));
        $actual = $this->renderHeadings(Blade::render($style, ['pdf' => true]));
        $this->assertCount(10, $actual);
        $this->assertSame($expected, $actual);
        foreach ($actual as $heading) {
            $this->assertSame('#f8dfccFF', $heading['style']['background_color']['hex']);
            $this->assertSame('avoid', $heading['style']['page_break_after']);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function renderHeadings(string $css): array
    {
        $content = '<div style="page-break-after: always">Portada</div><div class="report-document"><section class="report-page">';
        $titles = ['Función y posición', 'Qué necesita este signo', 'La casa y el territorio de experiencia', 'El regente y su posición', 'Integración de las piezas', 'Expresión Armónica', 'Expresión Des-Armónica por defecto', 'Expresión Des-Armónica por exceso', 'Armonización e integración final', 'Las cuatro puertas'];
        foreach ($titles as $index => $title) {
            $class = $index === 9 ? 'report-section-title' : 'report-block-title';
            $content .= '<h2 id="heading-'.$index.'" class="'.$class.'">'.$title.'</h2><div class="report-block-body"><p>Texto que debe acompañar al título y conservar sus espacios.</p></div>';
        }
        $content .= '</section></div>';
        $html = view('layouts.app', ['pdf' => true])->render();
        $dompdf = new Dompdf;
        $dompdf->getOptions()->setDefaultMediaType('print');
        $dompdf->setPaper('a4');
        $dompdf->loadHtml(str_replace('</body>', $css.$content.'</body>', $html));
        $headings = [];
        $dompdf->setCallbacks([['event' => 'end_frame', 'f' => static function ($frame, $canvas) use (&$headings): void {
            $node = $frame->get_node();
            if ($node->nodeName !== 'h2') {
                return;
            }
            $style = $frame->get_style();
            $properties = ['background_color', 'color', 'font_family', 'font_size', 'font_weight', 'font_style', 'line_height', 'text_align', 'display', 'min_width', 'max_width', 'padding_top', 'padding_right', 'padding_bottom', 'padding_left', 'margin_top', 'margin_right', 'margin_bottom', 'margin_left', 'border_top_width', 'border_right_width', 'border_bottom_width', 'border_left_width', 'border_top_left_radius', 'page_break_before', 'page_break_after', 'page_break_inside'];
            $values = [];
            foreach ($properties as $property) {
                $values[$property] = $style->$property;
            }
            $headings[$node->getAttribute('id')] = ['style' => $values, 'box' => $frame->get_border_box(), 'page' => $canvas->get_page_number()];
        }]]);
        $dompdf->render();

        return $headings;
    }

    public function test_responsive_is_screen_only_and_is_not_loaded_in_pdf(): void
    {
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(public_path('css/responsive.css')));
        $remaining = trim($css);
        while ($remaining !== '') {
            $this->assertMatchesRegularExpression('/^@media\s+screen\b[^{}]*\{/', $remaining);
            $position = strpos($remaining, '{') + 1;
            $depth = 1;
            for (; $position < strlen($remaining) && $depth > 0; $position++) {
                $depth += ($remaining[$position] === '{' ? 1 : 0) - ($remaining[$position] === '}' ? 1 : 0);
            }
            $this->assertSame(0, $depth);
            $remaining = trim(substr($remaining, $position));
        }
        $this->assertStringNotContainsString('responsive.css', view('layouts.app', ['pdf' => true])->render());
        $this->withoutVite();
        $this->assertMatchesRegularExpression('/href="[^"]*responsive\.css" media="screen"/', view('layouts.app', ['pdf' => false])->render());

    }

    public function test_report_splits_the_cover_and_chart_data_in_web_and_pdf(): void
    {
        foreach ([false, true] as $pdf) {
            $html = $this->reportHtml($pdf);
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($document);
            $cover = $xpath->query('//*[@data-cover-region="page"]')->item(0);
            $data = $xpath->query('//*[@data-cover-region="chart-data"]')->item(0);
            $this->assertStringContainsString('Carta natal de', $cover->textContent);
            $this->assertStringContainsString('Informe I', $cover->textContent);
            $this->assertStringNotContainsString('Matriz de aspectos', $cover->textContent);
            $this->assertStringNotContainsString('Elementos y su balance', $cover->textContent);
            $this->assertStringContainsString('Matriz de aspectos', $data->textContent);
            $this->assertStringContainsString('Elementos y su balance', $data->textContent);
            $this->assertStringContainsString('20 %', $data->textContent);
            $this->assertStringNotContainsString('Balance incompleto', $data->textContent);
            $this->assertSame(1, $xpath->query('.//*[@data-cover-region="wheel"]', $cover)->length);
            $this->assertSame(0, $xpath->query('.//*[@data-cover-region="wheel"]', $data)->length);
        }
        $this->assertStringContainsString('Balance incompleto: hay posiciones disponibles para 0 de los 10 planetas.', $this->reportHtml(true, snapshot: []));
    }

    public function test_pdf_cover_maximizes_wheel_and_keeps_matrix_and_elements_on_page_two_without_overlap(): void
    {
        foreach (['Persona de prueba', 'Persona de prueba con un nombre compuesto y dos apellidos suficientemente largos para ocupar varias líneas'] as $name) {
            $html = view('layouts.app', ['pdf' => true])->render();
            $html = str_replace('</body>', $this->reportHtml(true, $name).'</body>', $html);
            $dompdf = new Dompdf;
            $dompdf->getOptions()->setDefaultMediaType('print');
            $dompdf->setPaper('a4', 'portrait');
            $dompdf->loadHtml($html);
            $regions = [];
            $contentPages = [];
            $dompdf->setCallbacks([['event' => 'end_frame', 'f' => static function ($frame, $canvas) use (&$regions, &$contentPages): void {
                $node = $frame->get_node();
                if ($node instanceof \DOMText && trim($node->textContent) !== '') {
                    $contentPages[$canvas->get_page_number()] = true;
                }
                if ($node instanceof \DOMElement && $node->hasAttribute('data-cover-region')) {
                    $regions[$node->getAttribute('data-cover-region')] = [
                        'page' => $canvas->get_page_number(),
                        'box' => $frame->get_border_box(),
                    ];
                }
            }]]);
            $dompdf->render();

            foreach (['page', 'heading', 'wheel', 'metadata'] as $region) {
                $this->assertSame(1, $regions[$region]['page'], $region);
            }
            foreach (['chart-data', 'matrix', 'elements'] as $region) {
                $this->assertSame(2, $regions[$region]['page'], $region);
                [$x, $y, $width, $height] = $regions[$region]['box'];
                $this->assertGreaterThanOrEqual(51, $y);
                $this->assertLessThanOrEqual($dompdf->getCanvas()->get_height() - 51, $y + $height);
                $this->assertGreaterThanOrEqual(51, $x);
                $this->assertLessThanOrEqual($dompdf->getCanvas()->get_width() - 51, $x + $width);
            }
            $this->assertLessThanOrEqual($regions['wheel']['box'][1], $regions['heading']['box'][1] + $regions['heading']['box'][3]);
            $this->assertLessThanOrEqual($regions['metadata']['box'][1], $regions['wheel']['box'][1] + $regions['wheel']['box'][3]);
            $this->assertEqualsWithDelta(194 * 72 / 25.4, $regions['wheel']['box'][2], .1);
            $this->assertEqualsWithDelta($regions['wheel']['box'][2], $regions['wheel']['box'][3], .1);
            $this->assertLessThanOrEqual($regions['elements']['box'][1], $regions['matrix']['box'][1] + $regions['matrix']['box'][3]);
            $this->assertSame(4, $dompdf->getCanvas()->get_page_count());
            $this->assertSame([1, 2, 3, 4], array_keys($contentPages));
            $this->assertStringStartsWith('%PDF-', $dompdf->output());
        }
    }

    private function reportHtml(bool $pdf, string $name = 'Persona de prueba', ?array $snapshot = null): string
    {
        if ($snapshot === null) {
            $snapshot = [];
            foreach (['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto', 'true_node', 'mean_apogee', 'ascendant', 'midheaven'] as $index => $point) {
                $snapshot[$point] = ['longitude' => ($index * 30 + 29.99)];
            }
        }
        $chart = new Chart(['snapshot' => $snapshot, 'configuration' => ['houses' => 'placidus']]);
        $chart->setRelation('birthData', (new BirthData)->setRelation('place', new Place(['latitude' => 40.4])));
        $report = [
            'name' => $name, 'complete' => true, 'index' => [], 'doors' => [], 'opposites' => [],
            'sections' => ['opposites' => ['title' => 'Los opuestos complementarios']],
            'technical' => ['birth_date' => '01/01/2000', 'birth_time' => '12:00', 'place' => 'Madrid, España', 'zodiac' => 'tropical', 'houses' => 'Placidus'],
        ];
        $wheelImage = 'data:image/jpeg;base64,'.base64_encode(file_get_contents(__DIR__.'/../Fixtures/wheel.jpg'));

        return view('reports.phase-one.template', compact('chart', 'report', 'pdf', 'wheelImage'))->render();
    }
}
