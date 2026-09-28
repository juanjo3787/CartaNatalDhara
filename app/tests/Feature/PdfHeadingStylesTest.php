<?php

namespace Tests\Feature;

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
        $content = '<div class="report-document"><section class="report-page">';
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
}
