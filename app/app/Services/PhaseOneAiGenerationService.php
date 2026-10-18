<?php

namespace App\Services;

use App\Contracts\AiTextGenerator;
use App\Contracts\StructuredAiTextGenerator;
use App\Models\Chart;
use App\Models\ReportGeneration;
use App\Models\ReportJob;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PhaseOneAiGenerationService
{
    public function __construct(
        private readonly AiTextGenerator $generator,
        private readonly InteriorMapMaster $master,
        private readonly PhaseOneReportService $reports,
    ) {}

    public function generateChapter(Chart $chart, string $chapter, ReportJob $job): void
    {
        if (! config('ai.enabled')) {
            throw new RuntimeException('La generación de IA está desactivada.');
        }
        $integration = $chapter === 'integration';
        if (! $integration && ! in_array($chapter, InteriorMapMaster::DOORS, true)) {
            throw new RuntimeException('Capítulo del Informe I no válido.');
        }
        $facts = $this->master->facts($chart);
        $input = [
            'master_version' => InteriorMapMaster::VERSION,
            'chapter' => $chapter,
            'ASTROLOGICAL_FACTS' => $facts,
            'sections' => $integration ? InteriorMapMaster::INTEGRATION_TITLES : InteriorMapMaster::TITLES,
            'word_budget' => $integration ? '800–1050 palabras; themes contiene 3–5 temas diferenciados, preguntas y acciones.' : '780–1050 palabras. En opposite nombra el signo opuesto indicado y explica recursos y clave de integración; en los estados usa listas breves expresadas como párrafos.',
        ];
        if ($integration) {
            foreach (InteriorMapMaster::DOORS as $door) {
                $input['current_report'][$door] = $job->drafts[$door] ?? throw new RuntimeException('Falta una puerta del trabajo actual para integrar.');
            }
        }
        $system = $this->master->systemPrompt();
        $user = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (! $this->generator instanceof StructuredAiTextGenerator) {
            throw new RuntimeException('El nuevo generador requiere salida JSON estructurada.');
        }
        $pricing = config('ai.pricing', [])[(string) config('ai.model')] ?? null;
        if (! is_array($pricing) || ! isset($pricing['input'], $pricing['output'])) {
            throw new RuntimeException('Configura las tarifas del modelo de IA antes de generar el Informe I.');
        }
        $result = $this->generator->generateStructured($system, $user, $this->master->schema($integration), [
            'chart_id' => $chart->id, 'report_job_id' => $job->id,
            'door' => $chapter, 'stage' => 'interior_map',
        ]);
        $usage = $result['_usage'] ?? [];
        unset($result['_usage']);
        $this->master->validate($result, $integration);
        $this->master->validateFacts($result, $facts, $integration ? null : $chapter);
        $this->master->assertNoDuplicateParagraphs([$result]);
        DB::transaction(function () use ($chart, $chapter, $integration, $result, $job, $system, $user, $usage): void {
            $this->reports->storeChapter($chart, $integration ? null : $chapter, $result);
            $job->update(['drafts' => array_replace($job->drafts ?? [], [$chapter => $result])]);
            $this->recordGeneration($chart, $integration ? null : $chapter, $system, $user, $usage);
        });
    }

    public function validateReport(ReportJob $job): void
    {
        $words = $this->master->wordCount(['intro' => app(PhaseOneReportService::class)->introduction()]);
        foreach ([...InteriorMapMaster::DOORS, 'integration'] as $chapter) {
            $content = $job->drafts[$chapter] ?? throw new RuntimeException('Falta un capítulo del trabajo actual.');
            $this->master->validate($content, $chapter === 'integration');
            $words += $this->master->wordCount($content);
        }
        $this->master->assertNoDuplicateParagraphs(array_intersect_key($job->drafts, array_flip([...InteriorMapMaster::DOORS, 'integration'])));
        if ($words < 4000 || $words > 5500) {
            throw new RuntimeException("El informe tiene {$words} palabras; el modelo aprobado requiere 4000–5500.");
        }
        $this->reports->build($job->chart, storedOnly: true);
    }

    private function recordGeneration(Chart $chart, ?string $door, string $system, string $user, array $usage): void
    {
        $model = (string) config('ai.model');
        $pricing = config('ai.pricing', [])[$model];
        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);
        $exchange = (float) config('ai.usd_to_eur', 0.92);
        $inputCost = $input / 1_000_000 * $pricing['input'] * $exchange;
        $outputCost = $output / 1_000_000 * $pricing['output'] * $exchange;
        $subtotal = $inputCost + $outputCost;
        $rate = (float) config('ai.tax_rate', 21);
        ReportGeneration::create([
            'chart_id' => $chart->id, 'report_type' => 'fase-1-ai',
            'filename' => 'interior-map-'.($door ?? 'integration'),
            'size_bytes' => 0, 'checksum' => hash('sha256', $user.microtime(true)),
            'door' => $door, 'ai_assisted' => true, 'ai_model' => $model,
            'input_tokens' => $input, 'output_tokens' => $output, 'total_tokens' => $input + $output,
            'cost_input' => $inputCost, 'cost_output' => $outputCost, 'cost_subtotal' => $subtotal,
            'tax_rate' => $rate, 'tax_amount' => $subtotal * $rate / 100,
            'cost_total' => $subtotal * (1 + $rate / 100), 'cost_currency' => config('ai.currency', 'EUR'),
            'system_prompt' => $system, 'user_prompt' => $user,
        ]);
    }
}
