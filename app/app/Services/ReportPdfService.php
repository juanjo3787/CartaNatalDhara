<?php

namespace App\Services;

use App\Models\Chart;
use App\Models\ReportGeneration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReportPdfService
{
    public function store(Chart $chart, int $jobId): void
    {
        if (! $chart->natal_wheel_image) {
            throw new \RuntimeException('Falta la rueda astrológica para generar el PDF.');
        }
        $report = app(PhaseOneReportService::class)->build($chart);
        $pdf = $this->render($chart, $report, $chart->natal_wheel_image);
        $path = 'pdfs/'.$chart->id.'/job-'.$jobId.'-v'.PdfPageGeometry::VERSION.'.pdf';
        if (! Storage::disk('local')->put($path, $pdf)) {
            throw new \RuntimeException('PDF storage failed.');
        }
        DB::transaction(function () use ($chart, $path, $pdf): void {
            $chart->update(['phase_one_pdf' => $path, 'phase_one_pdf_generated_at' => now()]);
            ReportGeneration::updateOrCreate(['chart_id' => $chart->id, 'filename' => $path], ['report_type' => 'fase-1', 'size_bytes' => strlen($pdf), 'checksum' => hash('sha256', $pdf)]);
        });
    }

    public function render(Chart $chart, array $report, ?string $wheelImage): string
    {
        $renderStartedAt = hrtime(true);
        $renderTimeout = max(30, min(115, (int) config('reports.pdf_render_timeout', 110)));
        if (function_exists('set_time_limit') && ! set_time_limit($renderTimeout)) {
            Log::warning('The PHP execution limit could not be extended for PDF rendering.', [
                'chart_id' => $chart->id,
                'requested_timeout' => $renderTimeout,
            ]);
        }

        $wrapper = app('dompdf.wrapper')
            ->loadView('charts.report', compact('chart', 'report', 'wheelImage') + ['pdf' => true])
            ->setPaper('a4', 'portrait');
        $dompdf = $wrapper->getDomPDF();
        $renderedDoors = [];
        $doorStartPages = [];
        $expectedDoors = collect($report['doors'])
            ->filter(fn (array $door): bool => $report['sections'][$door['key']]['enabled'] ?? false)
            ->pluck('key')
            ->all();
        $dompdf->setCallbacks([[
            'event' => 'begin_frame',
            'f' => static function ($frame, $canvas) use (&$renderedDoors, &$doorStartPages): void {
                $node = $frame->get_node();
                if (! $node instanceof \DOMElement || ! $node->hasAttribute('data-door-key')) {
                    return;
                }

                $door = $node->getAttribute('data-door-key');
                if (isset($renderedDoors[$door])) {
                    return;
                }
                $renderedDoors[$door] = true;

                $page = $canvas->get_page_number();
                if ($page % 2 === 0) {
                    $canvas->new_page();
                }
                $doorStartPages[$door] = $canvas->get_page_number();
            },
        ]]);
        $dompdf->render();
        if (array_keys($doorStartPages) !== $expectedDoors) {
            throw new \RuntimeException('No se han paginado todas las puertas activas del informe.');
        }
        foreach ($doorStartPages as $door => $page) {
            if ($page % 2 === 0) {
                throw new \RuntimeException("La puerta {$door} no comienza en una página impar.");
            }
        }
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $header = 'SARANA VEDA · '.mb_strtoupper($report['name']);
        $canvas->page_script(static function (int $pageNumber, int $pageCount, $pageCanvas) use ($font, $header): void {
            if ($pageNumber === 1) {
                return;
            }
            $width = $pageCanvas->get_width();
            $height = $pageCanvas->get_height();
            $size = 7.5;
            $color = [0.54, 0.47, 0.41];
            $headerWidth = $pageCanvas->get_text_width($header, $font, $size);
            $pageCanvas->text(($width - $headerWidth) / 2, PdfPageGeometry::HEADER_TOP_PT, $header, $font, $size, $color);
            $pageCanvas->line(51, PdfPageGeometry::HEADER_BOTTOM_PT, $width - 51, PdfPageGeometry::HEADER_BOTTOM_PT, [0.85, 0.79, 0.74], 0.4);
            $footer = 'CARTA NATAL · FASE 1   /   '.$pageNumber;
            $footerWidth = $pageCanvas->get_text_width($footer, $font, $size);
            $pageCanvas->line(51, $height - PdfPageGeometry::FOOTER_TOP_FROM_BOTTOM_PT, $width - 51, $height - PdfPageGeometry::FOOTER_TOP_FROM_BOTTOM_PT, [0.85, 0.79, 0.74], 0.4);
            $pageCanvas->text(($width - $footerWidth) / 2, $height - PdfPageGeometry::FOOTER_TEXT_FROM_BOTTOM_PT, $footer, $font, $size, $color);
        });

        Log::info('Phase 1 PDF rendered.', [
            'chart_id' => $chart->id,
            'pages' => $canvas->get_page_count(),
            'duration_seconds' => round((hrtime(true) - $renderStartedAt) / 1_000_000_000, 2),
        ]);

        return $dompdf->output();
    }
}
