<?php

namespace App\Services;

use InvalidArgumentException;

final class PhaseOnePromptBuilder
{
    private const BLOCKS = [
        'shared_intro', 'function', 'sign', 'house', 'ruler',
        'integration', 'harmony', 'deficit', 'excess', 'closing',
    ];

    public function __construct(private readonly PhaseOneInstructionCatalog $instructionCatalog = new PhaseOneInstructionCatalog())
    {
    }

    /**
     * @param array<string, mixed> $context
     * @return array{system: string, user: string}
     */
    public function build(string $door, array $context): array
    {
        if (! in_array($door, ['sol', 'luna', 'ascendente', 'descendente'], true)) {
            throw new InvalidArgumentException("Puerta Fase 1 no válida: {$door}");
        }

        $instructions = $this->instructionCatalog->forDoor($door);
        $system = implode("\n", [
            'Eres una redactora editorial especializada en informes de astrología simbólica.',
            'Estas instrucciones son obligatorias. La fuente específica de esta puerta es: '.$instructions['source'],
            ...array_map(static fn (string $rule): string => '- '.$rule, $this->instructionCatalog->general()),
            ...array_map(static fn (string $rule): string => '- '.$rule, $instructions['rules']),
            'El cumplimiento se evalúa por bloque. No des una explicación genérica de astrología ni reutilices una idea con sinónimos para alcanzar la extensión.',
            'En function explica solo la función específica de esta puerta y cómo se distingue de las otras tres; todavía no interpretes signo, casa, regente ni estados.',
            'En sign responde cómo se expresa la función de esta puerta a través del signo; no copies una interpretación del mismo signo que serviría para otra puerta.',
            'En house desarrolla seis perspectivas distintas del territorio o eje indicado. Cada párrafo debe añadir una condición, proceso, responsabilidad, participación o cambio temporal nuevo.',
            'En ruler explica primero qué aporta el regente a la pregunta de esta puerta y después cómo su signo y casa lo matizan; si ya se presentó, úsalo solo como puente y aporta una consecuencia nueva.',
            'En integration construye consecuencias que solo aparecen al reunir puerta, signo, casa o eje y regente. Incluye una escena o proceso observable, no una suma de resúmenes.',
            'En harmony, deficit y excess conserva exactamente las características recibidas en datos_carta. Cada característica debe tener su propia pauta y ejemplo, en el mismo orden, y los ejemplos deben variar de contexto.',
            'En closing incluye síntesis, aprendizaje principal, recurso, riesgo, cinco preguntas de autoobservación y una frase central de integración con frases de apoyo.',
            'Devuelve exclusivamente un objeto JSON con las claves solicitadas y sin texto fuera del JSON.',
            'Cada bloque debe ser una matriz de strings. Para los bloques con características/pautas/ejemplos, cada string debe ser un fragmento HTML limpio y estructurado, con formato de lista obligatoriamente: <ol><li><strong>Característica:</strong> ...<br><strong>Pauta:</strong> ...<br><strong>Ejemplo:</strong> ...</li></ol> .',
            'No uses Markdown ni texto plano en bloques que requieren lista. Si no puedes seguir ese formato, reformula el bloque para que sí lo cumpla.',
            'No introduzcas etiquetas HTML peligrosas, scripts, estilos, enlaces ni atributos; solo se permiten p, ul, ol, li, strong, em y br.',
            'Prioriza claridad editorial, continuidad, orden lógico y lectura fluida. Cada string debe ser una unidad completa, no una concatenación de frases sueltas.',
        ]);

        $user = json_encode([
            'tarea' => 'Generar los bloques editoriales de una puerta del informe de Carta Natal Fase 1.',
            'puerta' => $door,
            'pregunta_central' => $context['question'] ?? null,
            'datos_carta' => $context,
            'continuidad' => [
                'puertas_anteriores' => $context['previous_doors'] ?? [],
                'regentes_ya_presentados' => $context['introduced_rulers'] ?? [],
                'regla' => 'Usa el contenido anterior como contexto. No repitas definiciones; añade una relación, consecuencia o matiz nuevo.',
            ],
            'bloques_obligatorios' => self::BLOCKS,
            'requisitos_de_extension' => [
                'shared_intro' => '3 párrafos de contexto y conexión personal.',
                'function' => '3 párrafos sobre la función de la puerta.',
                'sign' => '4 párrafos amplios sobre necesidades y recursos del signo.',
                'house' => '6 párrafos sobre el territorio vital y manifestaciones concretas.',
                'ruler' => '6 párrafos; 8 o más si hay doble regencia.',
                'integration' => '4 párrafos con una escena cotidiana.',
                'harmony' => '4 strings HTML de estilo lista, con 7 elementos de característica/pauta/ejemplo como mínimo.',
                'deficit' => '4 strings HTML de estilo lista, con 7 elementos de característica/pauta/ejemplo como mínimo.',
                'excess' => '4 strings HTML de estilo lista, con 7 elementos de característica/pauta/ejemplo como mínimo.',
                'closing' => 'Síntesis, aprendizaje principal, recurso, riesgo, preguntas de autoobservación y frase breve de integración.',
            ],
            'lista_de_comprobacion_antes_de_responder' => [
                'Cada bloque responde su propia pregunta y no anticipa el contenido de un bloque posterior.',
                'No hay párrafos equivalentes ni listas con ejemplos del mismo contexto repetido.',
                'Luna solo aborda necesidad emocional, cuidado y regulación; Ascendente solo inicio, orientación y ritmo; Descendente solo reciprocidad, acuerdos y dos subjetividades.',
                'No hay predicciones, diagnósticos, etiquetas fijas ni hechos biográficos atribuidos a la persona.',
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
