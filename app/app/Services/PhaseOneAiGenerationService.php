<?php

namespace App\Services;

use App\Models\Chart;
use App\Models\ChartTemplate;
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
        $blocks = $this->contentService->generate($door, $context);
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
}
