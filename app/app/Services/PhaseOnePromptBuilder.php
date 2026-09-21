<?php

namespace App\Services;

use InvalidArgumentException;

final class PhaseOnePromptBuilder
{
    private const BLOCKS = [
        'shared_intro', 'function', 'sign', 'house', 'ruler',
        'integration', 'harmony', 'deficit', 'excess', 'closing',
    ];

    /**
     * @param array<string, mixed> $context
     * @return array{system: string, user: string}
     */
    public function build(string $door, array $context): array
    {
        if (! in_array($door, ['sol', 'luna', 'ascendente', 'descendente'], true)) {
            throw new InvalidArgumentException("Puerta Fase 1 no válida: {$door}");
        }

        $system = <<<'PROMPT'
Eres una redactora editorial especializada en informes de astrología simbólica.
Escribe en español claro, cálido y no determinista. No hagas predicciones, diagnósticos
ni afirmaciones biográficas. El signo y la casa abren preguntas; no demuestran traumas,
historia familiar ni hechos de la vida de la persona.

Debes respetar estas reglas del dossier Fase 1:
- Diferencia la función de la puerta, el signo, la casa y el regente.
- Desarrolla la explicación, añade una pauta concreta y un ejemplo cotidiano reconocible.
- En el Descendente distingue deseo, petición, acuerdo y norma; no predigas qué persona llegará.
- En el Ascendente céntrate en cómo la persona entra y se posiciona; la casa VII solo es un contrapunto breve.
- Si hay dos regentes, explica ambos sin convertir el regente moderno lento en un rasgo individual exclusivo.
- Si un regente ya fue explicado, cambia la pregunta funcional y no repitas su definición base.
- Mantén la correspondencia: cada característica debe tener una pauta y un ejemplo en el mismo orden.

Devuelve exclusivamente un objeto JSON con las claves solicitadas. Cada valor debe ser una
lista de párrafos en español. No incluyas Markdown, encabezados ni texto fuera del JSON.
PROMPT;

        $user = json_encode([
            'tarea' => 'Generar los bloques editoriales de una puerta del informe de Carta Natal Fase 1.',
            'puerta' => $door,
            'pregunta_central' => $context['question'] ?? null,
            'datos_carta' => $context,
            'bloques_obligatorios' => self::BLOCKS,
            'requisitos_de_extension' => [
                'shared_intro' => '3 párrafos de contexto y conexión personal.',
                'function' => '3 párrafos sobre la función de la puerta.',
                'sign' => '4 párrafos amplios sobre necesidades y recursos del signo.',
                'house' => '6 párrafos sobre el territorio vital y manifestaciones concretas.',
                'ruler' => '6 párrafos; 8 o más si hay doble regencia.',
                'integration' => '4 párrafos con una escena cotidiana.',
                'harmony' => '4 párrafos y características, pautas y ejemplos correspondientes.',
                'deficit' => '4 párrafos y 7 características, pautas y ejemplos correspondientes.',
                'excess' => '4 párrafos y 7 características, pautas y ejemplos correspondientes.',
                'closing' => 'Recorrido desde defecto y exceso, equilibrio, preguntas y frases.',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return ['system' => $system, 'user' => $user];
    }

    /** @return list<string> */
    public function blocks(): array
    {
        return self::BLOCKS;
    }
}
