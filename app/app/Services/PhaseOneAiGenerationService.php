<?php

namespace App\Services;

use App\Domain\Astrology\DoorSequence;
use App\Domain\Astrology\RulerUsageRegistry;
use App\Models\Chart;
use App\Models\ChartTemplate;
use App\Models\ReportGeneration;
use App\Models\ReportJob;
use App\Services\Doors\AbstractDoorPipeline;
use App\Services\Doors\DoorPipelineFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class PhaseOneAiGenerationService
{
    public function __construct(
        private readonly PhaseOneReportService $reportService,
        private readonly PhaseOneAiContentService $contentService,
    ) {}

    public function generateDoor(Chart $chart, string $door): int
    {
        $context = $this->reportService->contextForDoor($chart, $door);
        $context['trace_id'] = (string) Str::uuid();
        $context['previous_doors'] = $this->previousDoorContent($chart, $door);
        $context['introduced_rulers'] = (new RulerUsageRegistry)->alreadyIntroduced($chart, $door);
        $blocks = $this->contentService->generate($door, $context);
        $usage = $this->contentService->usage();

        return DB::transaction(function () use ($chart, $door, $blocks, $context, $usage): int {
            $saved = $this->persistBlocks($chart, $door, $blocks, $context);
            $this->storeAiCost($chart, $door, $usage, $this->contentService->prompts());

            return $saved;
        });
    }

    /**
     * Generates one stage of a door's dossier at a time, caching a draft between requests so a failed
     * stage can be retried without regenerating the stages already completed and persisted.
     */
    public function generateDoorStage(Chart $chart, string $door, string $stage, string $sessionId, ?ReportJob $job = null): array
    {
        $stageIndex = array_search($stage, AbstractDoorPipeline::STAGES, true);
        if ($stageIndex === false) {
            throw new RuntimeException("Etapa no válida: {$stage}");
        }

        $key = 'phase1_ai_draft_v'.ReportState::SCHEMA_VERSION.'_'.ReportState::PROMPT_VERSION.'_'.$door.'_'.$chart->id.'_'.hash('sha256', $sessionId);
        $draft = $job ? ($job->drafts[$door] ?? null) : Cache::get($key);
        if (is_array($draft) && $stageIndex < ($draft['next'] ?? 0)) {
            return ['stage' => $stage, 'complete' => $draft['next'] === count(AbstractDoorPipeline::STAGES), 'blocks' => 0, 'already_completed' => true];
        }
        if ($stageIndex > 0 && (! is_array($draft) || ($draft['next'] ?? null) !== $stageIndex)) {
            throw new RuntimeException('La generación por etapas debe comenzar por la primera etapa y seguir el orden.');
        }
        $draft ??= [
            'next' => 0,
            'schema_version' => ReportState::SCHEMA_VERSION,
            'prompt_version' => ReportState::PROMPT_VERSION,
            'completed' => [],
            'usage' => ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0],
            'prompts' => [],
        ];

        $context = $this->reportService->contextForDoor($chart, $door);
        $context['trace_id'] = hash('sha256', $sessionId);
        $context['report_job_id'] = $job?->id;
        $result = $this->contentService->generateSunStage($stage, $context, $draft['completed']);
        $draft['completed'] = $this->mergeStageResult($draft['completed'], $result);
        $stateName = strtok($stage, '_');
        if (isset(ReportState::HEADINGS[$stateName])) {
            ReportTrace::record('draft', $draft['completed'][$stateName], ['chart_id' => $chart->id, 'trace_id' => $context['trace_id'], 'section_id' => $door.'.'.$stateName, 'stage' => $stage]);
        }
        foreach (array_keys($draft['usage']) as $tokenKey) {
            $draft['usage'][$tokenKey] += $this->contentService->usage()[$tokenKey];
        }
        $draft['prompts'][] = ['stage' => $stage, ...$this->contentService->prompts()];
        $draft['next'] = $stageIndex + 1;

        if ($draft['next'] < count(AbstractDoorPipeline::STAGES)) {
            if ($job) {
                $job->update(['drafts' => array_replace($job->drafts ?? [], [$door => $draft])]);
            } else {
                Cache::put($key, $draft, now()->addHour());
            }

            return ['stage' => $stage, 'complete' => false, 'blocks' => 0];
        }

        $blocks = DoorPipelineFactory::for($door)->render($draft['completed']);
        $prompts = [
            'system' => implode("\n\n", array_map(static fn (array $item): string => "[{$item['stage']}]\n{$item['system']}", $draft['prompts'])),
            'user' => implode("\n\n", array_map(static fn (array $item): string => "[{$item['stage']}]\n{$item['user']}", $draft['prompts'])),
        ];
        $saved = DB::transaction(function () use ($chart, $door, $blocks, $context, $draft, $prompts, $job): int {
            $saved = $this->persistBlocks($chart, $door, $blocks, $context);
            $this->storeAiCost($chart, $door, $draft['usage'], $prompts);
            if ($job) {
                $job->update(['drafts' => array_replace($job->drafts ?? [], [$door => $draft])]);
            }

            return $saved;
        });
        Cache::forget($key);

        return ['stage' => $stage, 'complete' => true, 'blocks' => $saved];
    }

    /** @deprecated Kept for backward compatibility with the sol-only stage route; delegates to generateDoorStage(). */
    public function generateSunStage(Chart $chart, string $stage, string $sessionId): array
    {
        return $this->generateDoorStage($chart, 'sol', $stage, $sessionId);
    }

    private function mergeStageResult(array $completed, array $result): array
    {
        return ReportStageMerger::merge($completed, $result);
    }

    private function persistBlocks(Chart $chart, string $door, array $blocks, array $context): int
    {
        $saved = 0;
        $chart->interpretations()
            ->where('door', $door)
            ->where('phase', 'fase-1')
            ->where('ai_assisted', true)
            ->delete();

        foreach ($blocks as $block => $paragraphs) {
            if (isset(ReportState::HEADINGS[$block])) {
                $state = ReportState::fromRendered($paragraphs, $door.'.'.$block, $block);
                ReportTrace::record('persistence', $state, ['chart_id' => $chart->id, 'trace_id' => $context['trace_id'] ?? null, 'section_id' => $door.'.'.$block]);
            }
            $template = ChartTemplate::firstOrCreate(
                [
                    'name' => "fase1_ai_{$door}_{$block}",
                    'version' => ReportState::SCHEMA_VERSION,
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

        $chart->forceFill(['phase_one_pdf' => null, 'phase_one_pdf_generated_at' => null])->save();

        return $saved;
    }

    /** @param array{input_tokens: int, output_tokens: int, total_tokens: int} $usage */
    /** @param array{system: string, user: string} $prompts */
    private function storeAiCost(Chart $chart, string $door, array $usage, array $prompts): void
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
            'system_prompt' => $prompts['system'],
            'user_prompt' => $prompts['user'],
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

        $excludedBlocks = in_array($door, [DoorSequence::ASCENDENTE, DoorSequence::DESCENDENTE], true)
            ? ['harmony', 'deficit', 'excess', 'harmonization']
            : [];

        $content = [];
        foreach ($rows as $row) {
            if (in_array($row->block, $excludedBlocks, true)) {
                continue;
            }

            $content[$row->door][$row->block] = $row->content;
        }

        return $content;
    }
}
