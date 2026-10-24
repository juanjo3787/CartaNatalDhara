<?php

namespace App\Services;

use App\Contracts\AiTextGenerator;
use App\Contracts\StructuredAiTextGenerator;
use App\Exceptions\ReportContentValidationException;
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
        $index = array_search($chapter, InteriorMapMaster::CHAPTERS, true);
        if ($index === false) {
            throw new RuntimeException('Bloque del Informe I no válido.');
        }
        $input = $this->input($chart, $chapter, $job);
        $input['editorial_target'] = [
            'word_target' => InteriorMapMaster::CHAPTER_TARGET_WORDS,
            'pages' => 'Siete páginas Word orientativas; sin paginación garantizada ni mínimo obligatorio de palabras.',
            'maximum_words' => InteriorMapMaster::CHAPTER_MAX_WORDS,
            'instruction' => 'Entre cinco y ocho características por concepto cuando los datos lo sostengan, con dos o tres párrafos sustanciales cada una. Omite lo que no tenga soporte; no rellenes para alcanzar el objetivo.',
        ];
        foreach (array_slice(InteriorMapMaster::CHAPTERS, 0, $index) as $previous) {
            $input['current_report'][$previous] = $job->drafts[$previous] ?? throw new RuntimeException("Falta el bloque anterior {$previous} del trabajo actual.");
        }
        [$result, $system, $user, $usage] = $this->request($input, $this->master->schema(), $chart, $job, $chapter);
        try {
            $this->master->validate($result);
            $this->master->validateFacts($result, $input['ASTROLOGICAL_FACTS'], $chapter === 'opposites' ? null : $chapter);
            $this->master->assertNoDuplicateParagraphs([...$input['current_report'], $result]);
        } catch (RuntimeException $exception) {
            $this->reject($job, $chapter, $result, $exception);
        }
        DB::transaction(function () use ($chart, $chapter, $result, $job, $system, $user, $usage): void {
            $this->reports->storeChapter($chart, $chapter === 'opposites' ? null : $chapter, $result);
            $drafts = array_replace($job->drafts ?? [], [$chapter => $result]);
            unset($drafts['_validation_feedback'][$chapter], $drafts['_validation_candidate'][$chapter], $drafts['_audited']);
            $job->update(['drafts' => $drafts]);
            $this->recordGeneration($chart, $chapter, $system, $user, $usage);
        });
    }

    public function auditReport(Chart $chart, ReportJob $job): void
    {
        $input = $this->input($chart, 'audit', $job);
        $input['mode'] = 'audit';
        foreach (InteriorMapMaster::CHAPTERS as $chapter) {
            $input['current_report'][$chapter] = $job->drafts[$chapter] ?? throw new RuntimeException("Falta el bloque {$chapter} para auditar.");
        }
        [$result, $system, $user, $usage] = $this->request($input, $this->master->auditSchema(), $chart, $job, 'audit');
        $chapters = $input['current_report'];
        try {
            if (array_keys($result) !== ['replacements'] || ! is_array($result['replacements']) || ! array_is_list($result['replacements'])) {
                throw new RuntimeException('La auditoría debe devolver una lista de reemplazos completos.');
            }
            $seen = [];
            foreach ($result['replacements'] as $replacement) {
                if (! is_array($replacement) || count($replacement) !== 2
                    || ! isset($replacement['chapter'], $replacement['content'])
                    || ! is_string($replacement['chapter'])
                    || ! in_array($replacement['chapter'], InteriorMapMaster::CHAPTERS, true)
                    || isset($seen[$replacement['chapter']]) || ! is_array($replacement['content'])) {
                    throw new RuntimeException('La auditoría contiene un bloque inválido o repetido.');
                }
                $seen[$replacement['chapter']] = true;
                $chapters[$replacement['chapter']] = $replacement['content'];
            }
            foreach ($chapters as $chapter => $content) {
                $this->master->validate($content);
                $this->master->validateFacts($content, $input['ASTROLOGICAL_FACTS'], $chapter === 'opposites' ? null : $chapter);
            }
            $this->master->assertNoDuplicateParagraphs($chapters);
        } catch (RuntimeException $exception) {
            $this->reject($job, 'audit', $result, $exception);
        }
        DB::transaction(function () use ($chart, $job, $chapters, $result, $system, $user, $usage): void {
            foreach ($result['replacements'] as $replacement) {
                $this->reports->storeChapter($chart, $replacement['chapter'] === 'opposites' ? null : $replacement['chapter'], $replacement['content']);
            }
            $drafts = array_replace($job->drafts, $chapters, ['_audited' => true]);
            unset($drafts['_validation_feedback']['audit'], $drafts['_validation_candidate']['audit']);
            $job->update(['drafts' => $drafts]);
            $this->recordGeneration($chart, 'audit', $system, $user, $usage);
        });
    }

    public function validateReport(ReportJob $job): void
    {
        if (($job->drafts['_audited'] ?? false) !== true) {
            throw new RuntimeException('Falta la auditoría conjunta de los cinco bloques.');
        }
        $facts = $this->master->facts($job->chart);
        $chapters = [];
        foreach (InteriorMapMaster::CHAPTERS as $chapter) {
            $content = $job->drafts[$chapter] ?? throw new RuntimeException('Falta un bloque del trabajo actual.');
            $this->master->validate($content);
            $this->master->validateFacts($content, $facts, $chapter === 'opposites' ? null : $chapter);
            $chapters[$chapter] = $content;
        }
        $this->master->assertNoDuplicateParagraphs($chapters);
        $this->reports->build($job->chart, storedOnly: true);
    }

    private function input(Chart $chart, string $chapter, ReportJob $job): array
    {
        $input = [
            'master_version' => InteriorMapMaster::VERSION,
            'chapter' => $chapter,
            'analysis_coverage' => $this->master->analysisCoverage($chapter),
            'ASTROLOGICAL_FACTS' => $this->master->facts($chart),
            'current_report' => [],
        ];
        if (isset($job->drafts['_validation_feedback'][$chapter])) {
            $input['previous_validation_error'] = $job->drafts['_validation_feedback'][$chapter];
            $input['revision_candidate'] = $job->drafts['_validation_candidate'][$chapter] ?? null;
            $input['measured_word_count'] = $this->master->wordCount($input['revision_candidate'] ?? []);
            $input['retry_instruction'] = 'Edita el borrador del mismo trabajo para corregir el rechazo y revisa a la vez TODOS los títulos y párrafos con la misma regla: '
                .'escribe todo íntegramente en español, sin inglés ni traducciones bilingües; sustituye títulos como «Alliances that amplify your reach (Alianzas que amplifican tu alcance)» por «Alianzas que amplifican tu alcance». '
                .'títulos en lenguaje llano que nombren rasgos vividos, sin introducciones, síntesis, resúmenes ni cierres, sin grados, cifras técnicas, comillas ni paréntesis '
                .'(si un título cita la casa, el signo o el aspecto entre paréntesis, elimina el paréntesis y explica ese dato en el párrafo); '
                .'en los párrafos, grados y orbes en grados y minutos o con palabras, nunca con decimales ni la palabra «orb»; '
                .'entre cinco y ocho características por concepto cuando haya soporte, dos o tres párrafos sustanciales cada una, segunda persona y sin nombres internos de datos. '
                .'Conserva la distinción entre tema principal, manifestación específica y explicación: los datos de regentes, cúspides y ocupantes fundamentan los párrafos, no son características por sí solos. '
                .'Ningún título de característica nombra casas numeradas, cúspides, regentes, dispositores, dignidades o aspectos, ni ejercicios, rutinas, rasgos corporales, carencias u otras personas; evita adjetivos con marca de género referidos al lector. '
                .'La voz propia en sentido figurado de expresión o afirmación no es un rasgo corporal: conserva ese significado si está sustentado. '
                .'Hazlo sin añadir relleno y sin perder el formato concepto/característica/párrafos.';
        }

        return $input;
    }

    private function request(array $input, array $schema, Chart $chart, ReportJob $job, string $chapter): array
    {
        if (! config('ai.enabled')) {
            throw new RuntimeException('La generación de IA está desactivada.');
        }
        if (! $this->generator instanceof StructuredAiTextGenerator) {
            throw new RuntimeException('El generador requiere salida JSON estructurada.');
        }
        $pricing = config('ai.pricing', [])[(string) config('ai.model')] ?? null;
        if (! is_array($pricing) || ! isset($pricing['input'], $pricing['output'])) {
            throw new RuntimeException('Configura las tarifas del modelo de IA antes de generar el Informe I.');
        }
        $system = $this->master->systemPrompt();
        $user = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $result = $this->generator->generateStructured($system, $user, $schema, [
            'chart_id' => $chart->id, 'report_job_id' => $job->id,
            'door' => $chapter, 'stage' => $chapter === 'audit' ? 'natal_audit' : 'natal_universal',
        ]);
        $usage = $result['_usage'] ?? [];
        unset($result['_usage']);

        return [$result, $system, $user, $usage];
    }

    private function reject(ReportJob $job, string $chapter, array $candidate, RuntimeException $exception): never
    {
        $drafts = $job->drafts ?? [];
        $drafts['_validation_feedback'][$chapter] = $exception->getMessage();
        $drafts['_validation_candidate'][$chapter] = $candidate;
        $job->update(['drafts' => $drafts]);
        throw new ReportContentValidationException($exception->getMessage(), previous: $exception);
    }

    private function recordGeneration(Chart $chart, string $chapter, string $system, string $user, array $usage): void
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
            'filename' => 'natal-universal-'.$chapter,
            'size_bytes' => 0, 'checksum' => hash('sha256', $user.microtime(true)),
            'door' => in_array($chapter, InteriorMapMaster::DOORS, true) ? $chapter : null,
            'ai_assisted' => true, 'ai_model' => $model,
            'input_tokens' => $input, 'output_tokens' => $output, 'total_tokens' => $input + $output,
            'cost_input' => $inputCost, 'cost_output' => $outputCost, 'cost_subtotal' => $subtotal,
            'tax_rate' => $rate, 'tax_amount' => $subtotal * $rate / 100,
            'cost_total' => $subtotal * (1 + $rate / 100), 'cost_currency' => config('ai.currency', 'EUR'),
            'system_prompt' => $system, 'user_prompt' => $user,
        ]);
    }
}
