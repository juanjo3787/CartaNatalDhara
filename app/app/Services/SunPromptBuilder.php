<?php

namespace App\Services;

final class SunPromptBuilder
{
    public const STAGES = ['foundation', 'harmony', 'deficit', 'excess', 'final'];

    public function build(string $stage, array $context, array $completed): array
    {
        if (! in_array($stage, self::STAGES, true)) {
            throw new \InvalidArgumentException("Etapa solar no válida: {$stage}");
        }

        $system = implode("\n", [
            'Redactas un dossier de astrología simbólica para una persona adulta sin conocimientos de astrología. Responde en español claro, cercano y no determinista.',
            'Los datos de ASTROLOGICAL_FACTS son canónicos: no recalcules, corrijas, completes ni inventes signos, casas, grados o posiciones. Interprétalos solamente. Una casa no equivale al signo tradicionalmente asociado a ella.',
            'Distingue la función del Sol (identidad, voluntad y elección) de la Luna, el Ascendente y el Descendente. Explica todo término astrológico antes de aplicarlo y traduce la combinación a escenas posibles de la vida cotidiana.',
            'Integra Sol, su signo, casa y regente con el signo y casa propios del regente. Si hay dos regentes, explica ambos individualmente. No reutilices texto genérico que sirva para otra carta.',
            'Mantén la segunda persona sin adjetivos de género si no se ha proporcionado un tratamiento gramatical. No afirmes hechos biográficos, diagnósticos ni predicciones. Evita "eres así", "siempre" y "tu carta demuestra".',
            'Cada string debe contener solo texto plano, sin Markdown, HTML, cabeceras ni numeración. Devuelve exclusivamente un objeto JSON con las claves solicitadas.',
        ]);

        $requirements = match ($stage) {
            'foundation' => [
                'shared_intro' => '3 párrafos breves de entrada a la puerta solar.',
                'function' => '3 párrafos de al menos 70 palabras: función psicológica, diferencia con las otras puertas y posición particular.',
                'sign' => '4 párrafos de al menos 70 palabras sobre lo que necesita ESTE Sol en su signo: necesidad, recurso, tensión y aprendizaje cotidiano.',
                'house' => '6 párrafos de al menos 80 palabras sobre el territorio de la casa, con situaciones reales; separa casa y signo.',
                'ruler' => '6 párrafos de al menos 80 palabras: significado del regente, su signo, su casa, su canal de expresión y consecuencias concretas.',
                'integration' => '4 párrafos de al menos 70 palabras sobre consecuencias que solo surgen al reunir todas las piezas, incluida una escena cotidiana.',
            ],
            'harmony' => $this->stateRequirements('harmony', $context),
            'deficit' => $this->stateRequirements('deficit', $context),
            'excess' => $this->stateRequirements('excess', $context),
            'final' => [
                'harmonization' => 'Desde el defecto: 2-4 párrafos y 3 puntos concretos. Desde el exceso: 2-4 párrafos y 3 puntos. Equilibrio: varios párrafos que integren todas las posiciones y 4 referencias observables.',
                'closing' => 'Un párrafo que explique cómo usar 5 preguntas solares específicas. Una frase central y cuatro frases de apoyo: empezar, recuperar medida, revisar y reunir lo aprendido.',
            ],
        };

        $user = [
            'stage' => $stage,
            'name' => $context['name'] ?? null,
            'question' => $context['question'] ?? null,
            'ASTROLOGICAL_FACTS' => $context['astrological_facts'] ?? [],
            'required_output' => $requirements,
            'output_shape' => match ($stage) {
                'foundation' => [
                    'shared_intro' => ['paragraphs' => ['texto']],
                    'function' => ['paragraphs' => ['texto']],
                    'sign' => ['paragraphs' => ['texto']],
                    'house' => ['paragraphs' => ['texto']],
                    'ruler' => ['paragraphs' => ['texto']],
                    'integration' => ['paragraphs' => ['texto']],
                ],
                'final' => [
                    'harmonization' => [
                        'from_deficit' => ['paragraphs' => ['texto'], 'points' => ['texto']],
                        'from_excess' => ['paragraphs' => ['texto'], 'points' => ['texto']],
                        'equilibrium' => ['paragraphs' => ['texto'], 'references' => ['texto']],
                    ],
                    'closing' => [
                        'question_intro' => ['texto'], 'questions' => ['texto'],
                        'central_phrase' => 'texto',
                        'support_phrases' => ['to_begin' => 'texto', 'to_restore_measure' => 'texto', 'to_review' => 'texto', 'to_integrate' => 'texto'],
                    ],
                ],
                default => [$stage => [
                    'development' => ['texto'],
                    'characteristics' => [['id' => 1, 'text' => 'texto']],
                    'guidelines' => [['id' => 1, 'text' => 'texto']],
                    'examples' => [['id' => 1, 'text' => 'texto']],
                ]],
            },
        ];

        if ($stage !== 'foundation') {
            $user['foundation'] = array_intersect_key($completed, array_flip(['function', 'sign', 'house', 'ruler', 'integration']));
        }
        if ($stage === 'final') {
            $user['states'] = array_intersect_key($completed, array_flip(['harmony', 'deficit', 'excess']));
        }

        return ['system' => $system, 'user' => json_encode($user, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    }

    private function stateRequirements(string $stage, array $context): array
    {
        $count = 7;
        return [
            'state' => $stage,
            'development' => '4-6 párrafos interpretativos de al menos 50 palabras cada uno ANTES de las listas. Explica mecanismo, protección o recurso, efecto inmediato, consecuencia posterior e intervención de signo, casa y regente.',
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
}
