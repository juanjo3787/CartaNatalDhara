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
        if (! config('ai.enabled')) {
            throw new RuntimeException('La generación de IA está desactivada.');
        }
        $integration = $chapter === 'integration';
        if (! $integration && ! in_array($chapter, InteriorMapMaster::DOORS, true)) {
            throw new RuntimeException('Capítulo del Informe I no válido.');
        }
        $facts = $this->master->facts($chart);
        $minimumWords = $integration ? InteriorMapMaster::INTEGRATION_MIN_WORDS : InteriorMapMaster::CHAPTER_MIN_WORDS;
        $maximumWords = $integration ? InteriorMapMaster::INTEGRATION_MAX_WORDS : InteriorMapMaster::CHAPTER_MAX_WORDS;
        $wordRange = $minimumWords.'–'.$maximumWords;
        $input = [
            'master_version' => InteriorMapMaster::VERSION,
            'chapter' => $chapter,
            'ASTROLOGICAL_FACTS' => $facts,
            'sections' => $integration ? InteriorMapMaster::INTEGRATION_TITLES : InteriorMapMaster::TITLES,
            'word_budget' => $integration ? "{$wordRange} palabras. Apunta a unas 1450 palabras sin rellenar ni eliminar el desarrollo necesario de los temas. themes contiene 3–5 objetos con título, desarrollo reflexivo, 3–5 puntos de trabajo y pregunta propios; cada tema relaciona al menos dos puertas e incluye una situación hipotética y una respuesta posible. Explora necesidades que conviven, sin métricas ni productividad." : "{$wordRange} palabras entre todas las secciones, con la profundidad explicativa del Word. Apunta a unas 1500 palabras sin rellenar ni recortar ejemplos necesarios para respetar un objetivo menor. Función general primero, sin signo particular. Después signo, casa y regentes verificados. Exactamente cinco comportamientos desarrollados por estado, con ejemplos y pautas. Reserva espacio suficiente al opuesto y su integración. Cierre: DON, NECESIDAD, SOMBRA, RECURSO, pregunta esencial y práctica sencilla.",
            'section_word_targets' => $integration ? [
                'purpose' => 100, 'work_points' => 80, 'themes' => 1100,
                'practice' => 160, 'essential_question' => 20,
            ] : [
                'function' => 145, 'entry_question' => 12, 'sign_traits' => 40,
                'sign' => 90, 'house_topics' => 35, 'house' => 75,
                'harmony' => 220, 'deficit' => 220, 'excess' => 220,
                'distinction_question' => 14, 'opposite' => 115, 'opposite_traits' => 35,
                'opposite_application' => 85, 'integration_key' => 15,
                'example' => 85, 'summary' => 50, 'essential_question' => 14, 'practice' => 40,
            ],
            'length_instruction' => 'Los objetivos son palabras TOTALES por sección, sumando todas sus entradas, no palabras por párrafo o elemento. Apunta al total de section_word_targets y comprueba el recuento antes de entregar. Desarrolla cada conducta con una situación concreta y una pauta breve; no reduzcas las explicaciones a etiquetas.',
        ];
        $feedback = $job->drafts['_validation_feedback'][$chapter] ?? null;
        if (is_string($feedback) && $feedback !== '') {
            $input['previous_validation_error'] = $feedback;
            $input['retry_instruction'] = "La respuesta anterior fue rechazada por este motivo. Corrígelo explícitamente en una nueva respuesta completa. El presupuesto de este capítulo es {$wordRange} palabras; prioriza preservar las explicaciones, ejemplos y pautas solicitados antes que ajustarte exactamente a los objetivos orientativos de las secciones.";
            $candidate = $job->drafts['_validation_candidate'][$chapter] ?? null;
            if (is_array($candidate)) {
                $input['revision_candidate'] = $candidate;
                $input['measured_word_count'] = $this->master->wordCount($candidate);
                foreach ($candidate as $section => $paragraphs) {
                    $input['measured_section_words'][$section] = $this->master->wordCount([$section => $paragraphs]);
                }
                $input['revision_target_words'] = array_sum($input['section_word_targets']);
                $input['retry_instruction'] = "Edita revision_candidate en vez de empezar otro desarrollo. El recuento medido por la aplicación es el correcto. Corrige el motivo de rechazo y conserva todas las secciones, explicaciones, ejemplos y datos canónicos. El capítulo debe quedar entre {$wordRange} palabras; los objetivos de sección son orientativos, no una razón para eliminar contenido necesario.".($integration ? ' Conserva los 3–5 temas y sus puntos de trabajo y preguntas.' : ' Conserva cinco conductas por cada estado.').' Entrega solo el JSON completo corregido, sin comentarios sobre la revisión.';
            }
        }
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
        try {
            $this->master->validate($result, $integration);
            $this->master->validateFacts($result, $facts, $integration ? null : $chapter);
            $this->master->assertNoDuplicateParagraphs([$result]);
        } catch (RuntimeException $exception) {
            $drafts = $job->drafts ?? [];
            $drafts['_validation_feedback'][$chapter] = $exception->getMessage();
            $drafts['_validation_candidate'][$chapter] = $result;
            $job->update(['drafts' => $drafts]);
            throw new ReportContentValidationException($exception->getMessage(), previous: $exception);
        }
        DB::transaction(function () use ($chart, $chapter, $integration, $result, $job, $system, $user, $usage): void {
            $this->reports->storeChapter($chart, $integration ? null : $chapter, $result);
            $drafts = array_replace($job->drafts ?? [], [$chapter => $result]);
            unset($drafts['_validation_feedback'][$chapter]);
            unset($drafts['_validation_candidate'][$chapter]);
            $job->update(['drafts' => $drafts]);
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
        if ($words < InteriorMapMaster::REPORT_MIN_WORDS || $words > InteriorMapMaster::REPORT_MAX_WORDS) {
            throw new RuntimeException("El informe tiene {$words} palabras; el modelo aprobado requiere ".InteriorMapMaster::REPORT_MIN_WORDS.'–'.InteriorMapMaster::REPORT_MAX_WORDS.'.');
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
