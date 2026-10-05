<?php

namespace Tests\Feature;

use App\Services\AspectMatrixBuilder;
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

    public function test_pdf_cover_stacks_the_wheel_and_aspect_matrix_on_cream(): void
    {
        $template = file_get_contents(resource_path('views/reports/phase-one/template.blade.php'));
        $style = substr($template, strpos($template, '<style>'), strpos($template, '</style>') + 8 - strpos($template, '<style>'));
        $pdfStyle = Blade::render($style, ['pdf' => true]);
        $cover = substr($template, strpos($template, '<section class="report-page report-cover">'), strpos($template, '</section>') - strpos($template, '<section class="report-page report-cover">'));

        $this->assertStringContainsString('.report-cover-visuals { width: 100%;', $pdfStyle);
        $this->assertStringContainsString('.report-cover-wheel-cell, .report-cover-matrix-cell { width: 100%; }', $pdfStyle);
        $this->assertStringContainsString('<div class="report-cover-visuals">', $cover);
        $this->assertStringNotContainsString('<table class="report-cover-visuals">', $cover);
        $this->assertStringContainsString('.report-cover .aspect-section--cover .aspect-position dt { white-space: normal; overflow-wrap: break-word; }', $pdfStyle);
        $this->assertStringContainsString('overflow: hidden;', $pdfStyle);
        $this->assertStringContainsString('top: -32.5mm; left: -32.5mm; width: 130mm; height: 130mm;', $pdfStyle);
        $this->assertStringContainsString('.report-cover .aspect-section--cover .aspect-position-glyph { font-family: DejaVu Sans, sans-serif; }', $pdfStyle);
        $this->assertStringContainsString('padding: 0; border: 0; background: transparent;', $pdfStyle);
        $this->assertStringContainsString('.report-document .report-cover .aspect-section--cover *:not(.aspect-diagonal) { background-color: #fffdf9 !important; }', $pdfStyle);
        $this->assertStringContainsString('.report-document .report-cover .aspect-section--cover .aspect-matrix-table td.aspect-diagonal { background-color: #eef1f2 !important; }', $pdfStyle);
        $this->assertStringContainsString('text-indent: 1.5em;', $pdfStyle);
        $this->assertStringContainsString('margin: 0 auto 1.25rem;', $pdfStyle);
        $this->assertStringContainsString('width: 128mm;', $pdfStyle);
        $this->assertStringContainsString('background: #fffdf9 !important;', $pdfStyle);
        $this->assertStringContainsString('data-background-color="#fffdf9"', $cover);
        $this->assertStringContainsString("@include('components.aspect-matrix'", $cover);
        $this->assertStringContainsString('[\'variant\' => \'cover\', \'showPositions\' => true, \'pdf\' => $pdf]', $cover);
        $this->assertLessThan(
            strpos($cover, 'report-cover-matrix-cell'),
            strpos($cover, 'report-cover-wheel-cell'),
            'The cover matrix must appear below the wheel.',
        );
    }

    public function test_pdf_cover_renders_the_wheel_and_full_matrix_on_one_page(): void
    {
        $template = file_get_contents(resource_path('views/reports/phase-one/template.blade.php'));
        $style = substr($template, strpos($template, '<style>'), strpos($template, '</style>') + 8 - strpos($template, '<style>'));
        $pdfStyle = Blade::render($style, ['pdf' => true]);
        $snapshot = [];
        foreach (['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto', 'true_node', 'mean_apogee', 'ascendant', 'midheaven'] as $point) {
            $snapshot[$point] = ['longitude' => 185];
        }
        $matrixData = app(AspectMatrixBuilder::class)->build($snapshot);
        $matrixHtml = view('components.aspect-matrix', $matrixData + ['variant' => 'cover', 'showPositions' => true, 'pdf' => true])->render();
        $this->assertStringNotContainsString('Aspectos entre los puntos calculados', $matrixHtml);
        $this->assertStringNotContainsString('>Punto</span>', $matrixHtml);
        $wheel = 'data:image/jpeg;base64,'.base64_encode(file_get_contents(__DIR__.'/../Fixtures/wheel.jpg'));
        $coverHtml = '<div class="report-document"><section class="report-page report-cover">'
            .'<div><div class="report-brand">ASTROLOGÍA SARANA VEDA</div><div style="margin-top: 3rem;"><span class="report-kicker">Dossier personal</span></div><h1>Carta natal de<br>Persona de prueba</h1><h2>Primer informe de la fase 1<br>Sol · Luna · Ascendente · Descendente</h2>'
            .'<div class="report-cover-visuals"><div class="report-cover-wheel-cell"><div class="report-cover-wheel"><div class="report-cover-wheel-container"><img class="report-cover-wheel-image" src="'.$wheel.'"></div></div></div>'
            .'<div class="report-cover-matrix-cell">'.$matrixHtml.'</div></div><div class="report-cover-name">Identidad y voluntad<br>Necesidades emocionales<br>Ritmo propio y vínculos</div></div>'
            .'<div class="report-cover-meta">28/09/1986 · 21:00 · Sevilla, España<br>Zodiaco tropical · Casas Placidus</div></section></div>';
        $html = view('layouts.app', ['pdf' => true])->render();
        $html = str_replace('</body>', $pdfStyle.$coverHtml.'</body>', $html);

        $dompdf = new Dompdf;
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $this->assertSame(1, $dompdf->getCanvas()->get_page_count());
        $this->assertStringStartsWith('%PDF-', $dompdf->output());
    }
}
