<?php

namespace App\Services;

use App\Models\Chart;
use App\Models\ChartTemplate;
use App\Domain\Astrology\DoorSequence;
use App\Domain\Astrology\RulerUsageRegistry;
use App\Models\ReportGeneration;
use Illuminate\Support\Facades\DB;

final class PhaseOneAiGenerationService
{
    public function __construct(
        private readonly PhaseOneReportService $reportService,
        private readonly PhaseOneAiContentService $contentService,
    ) {
    }

    public function generateDoor(Chart $chart, string $door): int
    {
        $context = $this->reportService->contextForDoor($chart, $door);
        $context['previous_doors'] = $this->previousDoorContent($chart, $door);
        $context['introduced_rulers'] = (new RulerUsageRegistry())->alreadyIntroduced($chart, $door);
        $blocks = $this->contentService->generate($door, $context);
        $usage = $this->contentService->usage();
        $this->storeAiCost($chart, $door, $usage);
        $saved = 0;

        DB::transaction(function () use ($chart, $door, $blocks, $context, &$saved): void {
            $chart->interpretations()
                ->where('door', $door)
                ->where('ai_assisted', true)
                ->delete();

            foreach ($blocks as $block => $paragraphs) {
                $template = ChartTemplate::firstOrCreate(
                    [
                        'name' => "fase1_ai_{$door}_{$block}",
                        'version' => 1,
                    ],
                    [
                        'door' => $door,
                        'block' => $block,
                        'content_type' => 'phase1_ai',
                        'status' => 'published',
                        'content' => 'Generación dinámica validada por PhaseOnePromptBuilder.',
                    ],
                );

                $chart->interpretations()->create([
                    'template_id' => $template->id,
                    'phase' => 'fase-1',
                    'door' => $door,
                    'block' => $block,
                    'content' => implode("\n\n", $paragraphs),
                    'ai_assisted' => true,
                    'rulers_used' => $context['ruler_keys'] ?? [],
                ]);
                $saved++;
            }
        });

        return $saved;
    }

    /** @param array{input_tokens: int, output_tokens: int, total_tokens: int} $usage */
    private function storeAiCost(Chart $chart, string $door, array $usage): void
    {
        $model = (string) config('ai.model');
        $pricing = config('ai.pricing', [])[$model] ?? ['input' => 0, 'output' => 0];
        $exchangeRate = (float) config('ai.usd_to_eur', 0.92);
        $inputCost = ($usage['input_tokens'] / 1_000_000) * (float) $pricing['input'] * $exchangeRate;
        $outputCost = ($usage['output_tokens'] / 1_000_000) * (float) $pricing['output'] * $exchangeRate;
        $subtotal = $inputCost + $outputCost;
        $taxRate = (float) config('ai.tax_rate', 21);
        $taxAmount = $subtotal * ($taxRate / 100);

        ReportGeneration::create([
            'chart_id' => $chart->id,
            'report_type' => 'fase-1-ai',
            'filename' => "puerta-{$door}",
            'size_bytes' => 0,
            'checksum' => hash('sha256', $chart->id.'|'.$door.'|'.microtime(true)),
            'door' => $door,
            'ai_assisted' => true,
            'ai_model' => $model,
            'input_tokens' => $usage['input_tokens'],
            'output_tokens' => $usage['output_tokens'],
            'total_tokens' => $usage['total_tokens'],
            'cost_input' => $inputCost,
            'cost_output' => $outputCost,
            'cost_subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'cost_total' => $subtotal + $taxAmount,
            'cost_currency' => config('ai.currency', 'EUR'),
        ]);
    }

    public function generateAll(Chart $chart): int
    {
        $saved = 0;

        foreach (DoorSequence::ORDER as $door) {
            $saved += $this->generateDoor($chart, $door);
        }

        return $saved;
    }

    /** @return array<string, array<string, string>> */
    private function previousDoorContent(Chart $chart, string $door): array
    {
        $rows = $chart->interpretations()
            ->whereIn('door', DoorSequence::doorsBefore($door))
            ->where('phase', 'fase-1')
            ->orderBy('id')
            ->get(['door', 'block', 'content']);

        $content = [];
        foreach ($rows as $row) {
            $content[$row->door][$row->block] = $row->content;
        }

        return $content;
    }
}
