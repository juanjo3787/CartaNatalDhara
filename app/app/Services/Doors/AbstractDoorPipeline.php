<?php

namespace App\Services\Doors;

use App\Services\PhaseOneInstructionCatalog;
use App\Services\SunAstrologicalFactValidator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Stage-by-stage generation (prompt + schema + validation + rendering) for one door. Each door has its
 * own concrete subclass (SolPipeline, LunaPipeline, AscendentePipeline, DescendentePipeline) so wording,
 * ruler count or schema shape for one door can be changed without any risk to the others. Splitting a
 * door into 9 small schema-validated stages instead of one giant completion is what avoids the
 * "finish_reason=length" truncation that used to happen on large dossiers.
 */
abstract class AbstractDoorPipeline
{
    public const STAGES = [
        'function', 'sign', 'house', 'ruler', 'integration',
        'harmony_development', 'harmony_characteristics', 'harmony_guidelines',
        'harmony_examples_1', 'harmony_examples_2',
        'deficit_development', 'deficit_characteristics', 'deficit_guidelines',
        'deficit_examples_1', 'deficit_examples_2',
        'excess_development', 'excess_characteristics', 'excess_guidelines',
        'excess_examples_1', 'excess_examples_2',
        'harmonization', 'closing',
    ];

    private const STATE_HEADINGS = [
        'harmony' => ['Pautas y consideraciones para reconocer este equilibrio', 'Ejemplos cotidianos de estas pautas'],
        'deficit' => ['Pautas y consideraciones para empezar a armonizar', 'Ejemplos cotidianos y formas de empezar a armonizar'],
        'excess' => ['Pautas y consideraciones para recuperar una medida adecuada', 'Ejemplos cotidianos y formas de recuperar medida'],
    ];

    abstract public function door(): string;

    abstract public function label(): string;

    abstract public function focus(): string;

    abstract public function rulerParagraphCount(): int;

    /**
     * Every door reproduces the Sol block structure exactly (function, sign, house/eje, ruler,
     * integration, harmony, deficit, excess, harmonización final, preguntas y frases de integración).
     */
    protected function functionRequirement(): string
    {
        return "3 párrafos de al menos 70 palabras: función psicológica de {$this->label()}, diferencia con las otras puertas y posición particular.";
    }

    protected function signRequirement(): string
    {
        return "4 párrafos de al menos 70 palabras sobre lo que necesita este signo aplicado a {$this->label()}: necesidad, recurso, tensión y aprendizaje cotidiano.";
    }

    protected function houseRequirement(): string
    {
        return '6 párrafos de al menos 80 palabras sobre el territorio de la casa o el eje correspondiente, con situaciones reales; separa casa y signo.';
    }

    protected function rulerRequirement(): string
    {
        return "{$this->rulerParagraphCount()} párrafos de al menos 80 palabras: significado del regente o regentes, su signo, su casa, su canal de expresión y consecuencias concretas. Si hay dos regentes, explica ambos individualmente antes de relacionarlos.";
    }

    protected function integrationRequirement(): string
    {
        return '4 párrafos de al menos 70 palabras sobre consecuencias que solo surgen al reunir todas las piezas, incluida una escena cotidiana.';
    }

    protected function harmonizationRequirement(): string
    {
        return 'Desde el defecto: 2-4 párrafos y 3 puntos concretos. Desde el exceso: 2-4 párrafos y 3 puntos. Equilibrio: varios párrafos que integren todas las posiciones y 4 referencias observables.';
    }

    protected function closingRequirement(): string
    {
        return 'Un párrafo que explique cómo usar 5 preguntas específicas de esta puerta. Una frase central y cuatro frases de apoyo: empezar, recuperar medida, revisar y reunir lo aprendido.';
    }

    /** Extra thematic guidance for a harmony/deficit/excess stage; empty string means "use the generic wording". */
    protected function stateThemes(string $stage): string
    {
        return '';
    }

    /** @return list<string> Extra system rules appended only for this door (sol keeps its current rules unchanged). */
    protected function additionalSystemRules(): array
    {
        return [];
    }

    /** @return array{system: string, user: string} */
    public function buildPrompt(string $stage, array $context, array $completed): array
    {
        if (! in_array($stage, self::STAGES, true)) {
            throw new InvalidArgumentException("Etapa no válida: {$stage}");
        }

        $instructions = (new PhaseOneInstructionCatalog())->forDoor($this->door());

        $system = implode("\n", [
            'Redactas un dossier de astrología simbólica para una persona adulta sin conocimientos de astrología. Responde en español claro, cercano y no determinista.',
            'Los datos de ASTROLOGICAL_FACTS son canónicos: no recalcules, corrijas, completes ni inventes signos, casas, grados o posiciones. Interprétalos solamente. Una casa no equivale al signo tradicionalmente asociado a ella.',
            "Esta puerta trata sobre {$this->label()}: {$this->focus()}.",
            ...array_map(static fn (string $rule): string => '- '.$rule, $instructions['rules']),
            ...array_map(static fn (string $rule): string => '- '.$rule, $this->additionalSystemRules()),
            'Explica todo término astrológico antes de aplicarlo y traduce la combinación a escenas posibles de la vida cotidiana. No reutilices texto genérico que sirva para otra carta.',
            'Mantén la segunda persona sin adjetivos de género si no se ha proporcionado un tratamiento gramatical. No afirmes hechos biográficos, diagnósticos ni predicciones. Evita "eres así", "siempre" y "tu carta demuestra".',
            'Cada string debe contener solo texto plano, sin Markdown, HTML, cabeceras ni numeración. Devuelve exclusivamente un objeto JSON con las claves solicitadas.',
        ]);

        $requirements = match ($stage) {
            'function' => [
                'shared_intro' => "3 párrafos breves de entrada a la puerta de {$this->label()}.",
                'function' => $this->functionRequirement(),
            ],
            'sign' => ['sign' => $this->signRequirement()],
            'house' => ['house' => $this->houseRequirement()],
            'ruler' => ['ruler' => $this->rulerRequirement()],
            'integration' => ['integration' => $this->integrationRequirement()],
            'harmonization' => ['harmonization' => $this->harmonizationRequirement()],
            'closing' => ['closing' => $this->closingRequirement()],
            default => $this->statePartRequirements($stage, $context),
        };

        $closingShape = [
            'question_intro' => ['texto'], 'questions' => ['texto'],
            'central_phrase' => 'texto',
            'support_phrases' => ['to_begin' => 'texto', 'to_restore_measure' => 'texto', 'to_review' => 'texto', 'to_integrate' => 'texto'],
        ];

        $user = [
            'stage' => $stage,
            'door' => $this->door(),
            'name' => $context['name'] ?? null,
            'question' => $context['question'] ?? null,
            'ASTROLOGICAL_FACTS' => $context['astrological_facts'] ?? [],
            'required_output' => $requirements,
            'output_shape' => match ($stage) {
                'function' => [
                    'shared_intro' => ['paragraphs' => ['texto']],
                    'function' => ['paragraphs' => ['texto']],
                ],
                'sign', 'house', 'ruler', 'integration' => [$stage => ['paragraphs' => ['texto']]],
                'harmonization' => [
                    'harmonization' => [
                        'from_deficit' => ['paragraphs' => ['texto'], 'points' => ['texto']],
                        'from_excess' => ['paragraphs' => ['texto'], 'points' => ['texto']],
                        'equilibrium' => ['paragraphs' => ['texto'], 'references' => ['texto']],
                    ],
                ],
                'closing' => ['closing' => $closingShape],
                default => $this->statePartOutputShape($stage),
            },
        ];

        if ($stage !== 'function') {
            $user['foundation'] = array_intersect_key($completed, array_flip(['function', 'sign', 'house', 'ruler', 'integration']));
        }
        if ($stage === 'harmonization') {
            $user['states'] = array_intersect_key($completed, array_flip(['harmony', 'deficit', 'excess']));
        }

        [$state, $part] = $this->statePart($stage);
        if ($state !== null && in_array($part, ['guidelines', 'examples_1', 'examples_2'], true)) {
            $user['state_content'] = $completed[$state] ?? [];
        }

        return ['system' => $system, 'user' => json_encode($user, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    }

    private function statePartRequirements(string $stage, array $context): array
    {
        [$state, $part] = $this->statePart($stage);
        if ($state === null || $part === null) {
            throw new InvalidArgumentException("Etapa no válida: {$stage}");
        }
        $all = $this->stateRequirements($state, $context);
        return match ($part) {
            'development' => ['state' => $state, 'development' => $all['development'], 'special_rule' => $all['special_rule']],
            'characteristics' => ['state' => $state, 'characteristics' => $all['characteristics'], 'characteristic_seeds' => $all['characteristic_seeds']],
            'guidelines' => ['state' => $state, 'guidelines' => $all['guidelines'], 'correspondence' => $all['correspondence']],
            'examples_1' => ['state' => $state, 'examples' => 'Genera únicamente las escenas con ID 1, 2 y 3. '.$all['examples'], 'correspondence' => $all['correspondence']],
            'examples_2' => ['state' => $state, 'examples' => 'Genera únicamente las escenas con ID 4, 5, 6 y 7. '.$all['examples'], 'correspondence' => $all['correspondence']],
        };
    }

    private function statePartOutputShape(string $stage): array
    {
        [$state, $part] = $this->statePart($stage);
        $key = str_starts_with((string) $part, 'examples_') ? 'examples' : $part;
        return [$state => [$key => $key === 'development' ? ['texto'] : [['id' => 1, 'text' => 'texto']]]];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function statePart(string $stage): array
    {
        if (preg_match('/^(harmony|deficit|excess)_(development|characteristics|guidelines|examples_[12])$/', $stage, $matches)) {
            return [$matches[1], $matches[2]];
        }
        return [null, null];
    }

    private function stateRequirements(string $stage, array $context): array
    {
        $count = 7;
        $themes = $this->stateThemes($stage);
        $development = '4-6 párrafos interpretativos de al menos 50 palabras cada uno ANTES de las listas. Explica mecanismo, protección o recurso, efecto inmediato, consecuencia posterior e intervención de signo, casa y regente.';
        if ($themes !== '') {
            $development .= ' '.$themes;
        }

        return [
            'state' => $stage,
            'development' => $development,
            'characteristics' => "{$count} características breves, numeradas con IDs del 1 al {$count}.",
            'guidelines' => "{$count} pautas de 40-90 palabras cada una. Cada pauta observa, distingue, comprueba y explica qué señala equilibrio o recuperación de medida. Usa los mismos IDs.",
            'examples' => "{$count} escenas narrativas de 80-150 palabras: contexto, situación, reacción, experiencia interna, respuesta y aprendizaje. Usa los mismos IDs.",
            'correspondence' => 'Cada pauta debe desarrollar exclusivamente la característica con el mismo ID; cada ejemplo debe escenificar la pauta con ese mismo ID. Mantén el orden 1 a 7 en las tres listas.',
            'characteristic_seeds' => $context['states'][$stage]['characteristics'] ?? [],
            'special_rule' => match ($stage) {
                'harmony' => 'Explica cómo se reconoce esta combinación cuando tiene una medida adecuada.',
                'deficit' => 'Explica qué capacidad tiene poco espacio y qué intenta proteger.',
                'excess' => 'El exceso es una capacidad útil que ocupa demasiado espacio, no un defecto moral. Explica recompensa inmediata, coste posterior y cómo recuperar medida.',
            },
        ];
    }

    public function schemaForStage(string $stage): array
    {
        $paragraphs = $this->schemaObject(['paragraphs' => $this->schemaStrings()]);
        $item = $this->schemaObject(['id' => ['type' => 'integer'], 'text' => ['type' => 'string']]);
        $closing = $this->schemaObject([
            'question_intro' => $this->schemaStrings(), 'questions' => $this->schemaStrings(),
            'central_phrase' => ['type' => 'string'],
            'support_phrases' => $this->schemaObject(array_fill_keys(
                ['to_begin', 'to_restore_measure', 'to_review', 'to_integrate'], ['type' => 'string'],
            )),
        ]);

        return match ($stage) {
            'function' => $this->schemaObject(['shared_intro' => $paragraphs, 'function' => $paragraphs]),
            'sign', 'house', 'ruler', 'integration' => $this->schemaObject([$stage => $paragraphs]),
            'harmonization' => $this->schemaObject([
                'harmonization' => $this->schemaObject([
                    'from_deficit' => $this->schemaObject(['paragraphs' => $this->schemaStrings(), 'points' => $this->schemaStrings()]),
                    'from_excess' => $this->schemaObject(['paragraphs' => $this->schemaStrings(), 'points' => $this->schemaStrings()]),
                    'equilibrium' => $this->schemaObject(['paragraphs' => $this->schemaStrings(), 'references' => $this->schemaStrings()]),
                ]),
            ]),
            'closing' => $this->schemaObject(['closing' => $closing]),
            default => $this->statePartSchema($stage, $item),
        };
    }

    private function statePartSchema(string $stage, array $item): array
    {
        [$state, $part] = $this->statePart($stage);
        if ($state === null || $part === null) {
            throw new InvalidArgumentException("Etapa no válida: {$stage}");
        }
        $key = str_starts_with($part, 'examples_') ? 'examples' : $part;
        $value = $key === 'development' ? $this->schemaStrings() : ['type' => 'array', 'items' => $item];
        return $this->schemaObject([$state => $this->schemaObject([$key => $value])]);
    }

    private function schemaStrings(): array
    {
        return ['type' => 'array', 'items' => ['type' => 'string']];
    }

    private function schemaObject(array $properties): array
    {
        return [
            'type' => 'object', 'properties' => $properties,
            'required' => array_keys($properties), 'additionalProperties' => false,
        ];
    }

    public function validate(string $stage, array $result, array $context): array
    {
        unset($result['_usage']);

        if (in_array($stage, ['function', 'sign', 'house', 'ruler', 'integration'], true)) {
            $counts = [
                'shared_intro' => 3, 'function' => 3, 'sign' => 4, 'house' => 6,
                'ruler' => $this->rulerParagraphCount(), 'integration' => 4,
            ];
            foreach ($counts as $key => $count) {
                if ($key !== $stage && ! ($stage === 'function' && $key === 'shared_intro')) {
                    continue;
                }
                $minimumWords = match ($key) {
                    'shared_intro' => 20,
                    'house', 'ruler' => 80,
                    default => 70,
                };
                $this->validateParagraphs($result[$key]['paragraphs'] ?? null, $count, $count, $minimumWords, $key);
            }
        } elseif ($this->statePart($stage)[0] !== null) {
            $this->validateStatePart($stage, $result);
        } elseif ($stage === 'harmonization') {
            $harmonization = $result['harmonization'] ?? [];
            foreach (['from_deficit', 'from_excess'] as $key) {
                $this->validateParagraphs($harmonization[$key]['paragraphs'] ?? null, 2, 4, 45, $key);
                $this->validateParagraphs($harmonization[$key]['points'] ?? null, 3, 3, 8, "{$key}.points");
            }
            $this->validateParagraphs($harmonization['equilibrium']['paragraphs'] ?? null, 2, 4, 45, 'equilibrium');
            $this->validateParagraphs($harmonization['equilibrium']['references'] ?? null, 4, 4, 6, 'references');
        } elseif ($stage === 'closing') {
            $closing = $result['closing'] ?? [];
            $this->validateParagraphs($closing['question_intro'] ?? null, 1, 2, 25, 'question_intro');
            $this->validateParagraphs($closing['questions'] ?? null, 5, 5, 7, 'questions');
            foreach ($closing['questions'] as $question) {
                if (! str_contains($question, '?')) {
                    throw new RuntimeException('Las preguntas de autoobservación deben formularse como preguntas.');
                }
            }
            $this->validatePlainText($closing['central_phrase'] ?? null, 5, 'central_phrase');
            foreach (['to_begin', 'to_restore_measure', 'to_review', 'to_integrate'] as $key) {
                $this->validatePlainText($closing['support_phrases'][$key] ?? null, 5, "support_phrases.{$key}");
            }
        } else {
            throw new RuntimeException("Etapa desconocida: {$stage}");
        }

        (new SunAstrologicalFactValidator())->validate($result, $context['astrological_facts']);
        $this->assertNoGenericPhrases($result);

        return $result;
    }

    private function validateStatePart(string $stage, array $result): void
    {
        [$stateName, $part] = $this->statePart($stage);
        $state = $result[$stateName] ?? null;
        if (! is_array($state)) {
            throw new RuntimeException("Falta el estado {$stateName}.");
        }
        if ($part === 'development') {
            $this->validateParagraphs($state['development'] ?? null, 4, 6, 50, "{$stateName}.development");
            return;
        }
        $key = str_starts_with($part, 'examples_') ? 'examples' : $part;
        $expectedIds = match ($part) {
            'examples_1' => range(1, 3),
            'examples_2' => range(4, 7),
            default => range(1, 7),
        };
        $items = $state[$key] ?? null;
        if (! is_array($items) || count($items) !== count($expectedIds)) {
            throw new RuntimeException("{$stateName}.{$key} debe contener ".count($expectedIds).' elementos.');
        }
        $minimumWords = ['characteristics' => 3, 'guidelines' => 40, 'examples' => 80][$key];
        foreach (array_values($items) as $index => $item) {
            $id = $expectedIds[$index];
            if (! is_array($item) || ($item['id'] ?? null) !== $id) {
                throw new RuntimeException("ID incorrecto en {$stateName}.{$key}; se esperaba {$id}.");
            }
            $this->validatePlainText($item['text'] ?? null, $minimumWords, "{$stateName}.{$key}.{$id}");
            if ($key === 'characteristics' && $this->wordCount($item['text']) > 30) {
                throw new RuntimeException("La característica {$id} de {$stateName} debe ser breve.");
            }
        }
    }

    /**
     * Reject verbatim reuse of known generic sentences across doors (e.g. copy-pasting the Sol wording
     * instead of writing a Luna/Ascendente/Descendente-specific paragraph). Comparison is case-insensitive.
     */
    private const BANNED_GENERIC_PHRASES = [
        'recupera espacio para la capacidad que apenas pudo expresarse',
        'el punto de equilibrio permite elegir cuándo utilizar el recurso',
        'encuentra una medida adecuada: tiene espacio suficiente para participar sin ocuparlo todo',
    ];

    private function assertNoGenericPhrases(array $result): void
    {
        $texts = [];
        array_walk_recursive($result, static function (mixed $value) use (&$texts): void {
            if (is_string($value)) {
                $texts[] = mb_strtolower($value);
            }
        });

        foreach (self::BANNED_GENERIC_PHRASES as $phrase) {
            foreach ($texts as $text) {
                if (str_contains($text, $phrase)) {
                    throw new RuntimeException("El texto reutiliza una frase genérica no específica de esta puerta: \"{$phrase}\".");
                }
            }
        }
    }

    private function validateParagraphs(mixed $items, int $minimum, int $maximum, int $minimumWords, string $path): void
    {
        if (! is_array($items) || count($items) < $minimum || count($items) > $maximum) {
            throw new RuntimeException("{$path} debe contener entre {$minimum} y {$maximum} textos.");
        }
        foreach ($items as $index => $item) {
            $this->validatePlainText($item, $minimumWords, "{$path}.{$index}");
        }
    }

    private function validatePlainText(mixed $text, int $minimumWords, string $path): void
    {
        if (! is_string($text) || trim($text) === '' || preg_match('/<[^>]*>|https?:\/\/|\*\*/iu', $text)) {
            throw new RuntimeException("Texto inválido en {$path}.");
        }
        if ($this->wordCount($text) < $minimumWords) {
            throw new RuntimeException("{$path} necesita al menos {$minimumWords} palabras desarrolladas.");
        }
    }

    private function wordCount(string $text): int
    {
        return preg_match_all('/[\p{L}\p{N}]+/u', $text);
    }

    public function render(array $content): array
    {
        $blocks = [];
        foreach (['shared_intro', 'function', 'sign', 'house', 'ruler', 'integration'] as $key) {
            $blocks[$key] = $this->renderParagraphs($content[$key]['paragraphs']);
        }

        foreach (self::STATE_HEADINGS as $key => [$guidelineHeading, $exampleHeading]) {
            $state = $content[$key];
            $blocks[$key] = [
                ...$this->renderParagraphs($state['development']),
                '<h3>Características que puedes observar</h3>',
                $this->orderedList(array_column($state['characteristics'], 'text')),
                '<h3>'.e($guidelineHeading).'</h3>',
                $this->orderedList(array_column($state['guidelines'], 'text')),
                '<h3>'.e($exampleHeading).'</h3>',
                $this->orderedList(array_column($state['examples'], 'text')),
            ];
        }

        $harmonization = $content['harmonization'];
        $blocks['harmonization'] = [
            '<h3>Desde el defecto</h3>',
            ...$this->renderParagraphs($harmonization['from_deficit']['paragraphs']),
            '<p>Puntos concretos para comenzar:</p>',
            $this->orderedList($harmonization['from_deficit']['points']),
            '<h3>Desde el exceso</h3>',
            ...$this->renderParagraphs($harmonization['from_excess']['paragraphs']),
            '<p>Puntos concretos para recuperar medida:</p>',
            $this->orderedList($harmonization['from_excess']['points']),
            '<h3>El punto de equilibrio</h3>',
            ...$this->renderParagraphs($harmonization['equilibrium']['paragraphs']),
            '<p>Referencias para reconocer ese equilibrio:</p>',
            $this->unorderedList($harmonization['equilibrium']['references']),
        ];

        $closing = $content['closing'];
        $blocks['closing'] = [
            '<h3>Preguntas de autoobservación</h3>',
            ...$this->renderParagraphs($closing['question_intro']),
            $this->orderedList($closing['questions']),
            '<h3>Frases de integración</h3>',
            '<p>Frase central: «'.e($closing['central_phrase']).'»</p>',
            '<p>Para empezar: «'.e($closing['support_phrases']['to_begin']).'»</p>',
            '<p>Para recuperar medida: «'.e($closing['support_phrases']['to_restore_measure']).'»</p>',
            '<p>Para revisar: «'.e($closing['support_phrases']['to_review']).'»</p>',
            '<p>Para reunir lo aprendido: «'.e($closing['support_phrases']['to_integrate']).'»</p>',
        ];

        return $blocks;
    }

    private function renderParagraphs(array $items): array
    {
        return array_map(static fn (string $text): string => '<p>'.e($text).'</p>', $items);
    }

    private function orderedList(array $items): string
    {
        return '<ol>'.implode('', array_map(static fn (string $text): string => '<li>'.e($text).'</li>', $items)).'</ol>';
    }

    private function unorderedList(array $items): string
    {
        return '<ul>'.implode('', array_map(static fn (string $text): string => '<li>'.e($text).'</li>', $items)).'</ul>';
    }
}
