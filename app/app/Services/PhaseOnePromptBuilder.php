<?php

namespace App\Services;

use InvalidArgumentException;

final class PhaseOnePromptBuilder
{
    private const SOL_BLOCKS = [
        'shared_intro', 'function', 'sign', 'house', 'ruler',
        'integration', 'harmony', 'deficit', 'excess', 'harmonization', 'closing',
    ];

    private const STANDARD_BLOCKS = [
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
        $stateFormat = in_array($door, ['luna', 'descendente'], true)
            ? 'Esquema HTML obligatorio para esta puerta: harmony, deficit y excess deben devolver un string independiente por característica, con exactamente <ol><li><strong>Característica:</strong> ...<br><strong>Pauta:</strong> ...<br><strong>Ejemplo:</strong> ...</li></ol>. No incluyas Desarrollo ni agrupes características.'
            : 'Esquema HTML obligatorio: cada <li> debe incluir exactamente una <strong>Característica:</strong>, una <strong>Desarrollo:</strong>, una <strong>Pauta:</strong> y un <strong>Ejemplo:</strong>, separados por <br>. Cada estado debe conservar un único <ol> y cada característica ocupa un <li> independiente.';
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
            'En harmony, deficit y excess conserva exactamente las características recibidas en datos_carta. Cada característica debe tener su propio Desarrollo, Pauta y Ejemplo, en el mismo orden, y los ejemplos deben variar de contexto.',
            'En harmonization trabaja únicamente con las características ya desarrolladas: separa el recorrido desde defecto y desde exceso, e indica reconocimiento, práctica, resultado y ajuste.',
            'En closing incluye, en ese orden, síntesis, aprendizaje principal, recurso, riesgo, exactamente cinco preguntas de autoobservación y una frase central con frases breves de apoyo.',
            'Profundidad mínima obligatoria: cada párrafo debe contener una idea desarrollada, un matiz y una consecuencia o forma de observación. No cuentes frases introductorias, definiciones repetidas ni cierres genéricos como desarrollo.',
            'Regla de no repetición endurecida: antes de redactar cada bloque, identifica qué conceptos, ejemplos y regentes ya aparecen en puertas_anteriores. Solo puedes retomarlos como puente breve seguido de una consecuencia nueva; no repitas definiciones, escenas, pautas ni frases con sinónimos.',
            'Devuelve exclusivamente un objeto JSON con las claves solicitadas y sin texto fuera del JSON.',
            'Esquema JSON obligatorio: la respuesta raíz debe ser un objeto; debe contener exactamente las claves de bloques_obligatorios; cada valor debe ser un array JSON de strings no vacíos; no uses objetos, null, claves adicionales, Markdown ni texto fuera del JSON.',
            $stateFormat,
            'En los bloques narrativos usa strings HTML con párrafos completos (<p>...</p>) o texto limpio, pero nunca mezcles JSON dentro del HTML ni HTML sin cerrar. Escapa comillas dentro del JSON y conserva caracteres Unicode válidos.',
            'No uses Markdown, listas planas ni etiquetas HTML fuera de p, ol, ul, li, strong, em y br. No uses atributos, enlaces, estilos, scripts ni etiquetas sin cerrar.',
            'No introduzcas etiquetas HTML peligrosas, scripts, estilos, enlaces ni atributos; solo se permiten p, ul, ol, li, strong, em y br.',
            'Prioriza claridad editorial, continuidad, orden lógico y lectura fluida. Cada string debe ser una unidad completa, no una concatenación de frases sueltas.',
        ]);

        $user = [
            'tarea' => 'Generar los bloques editoriales de una puerta del informe de Carta Natal Fase 1.',
            'puerta' => $door,
            'pregunta_central' => $context['question'] ?? null,
            'datos_carta' => $this->structuredChartData($context),
            'continuidad' => [
                'puertas_anteriores' => $context['previous_doors'] ?? [],
                'regentes_ya_presentados' => $context['introduced_rulers'] ?? [],
                'regla' => 'Usa el contenido anterior como contexto. No repitas definiciones; añade una relación, consecuencia o matiz nuevo.',
            ],
            'bloques_obligatorios' => $this->blocks($door),
            'requisitos_de_desarrollo' => [
                'shared_intro' => 'Transición breve hacia la puerta, pregunta central y separación de piezas; no interpretes todavía signo, casa, regente ni estados.',
                'function' => 'Solo la función específica de la puerta y su diferencia con las otras tres.',
                'sign' => 'Cómo el signo modifica específicamente la función de la puerta, con necesidades, recursos y tensiones propias.',
                'house' => 'Seis perspectivas diferentes del territorio: experiencia, necesidad, participación, responsabilidad, condiciones y evolución temporal.',
                'ruler' => 'Aporte del regente, después signo y casa del regente, con necesidades, consecuencias y manifestaciones nuevas.',
                'integration' => 'Consecuencias que solo aparecen al reunir todas las piezas, con proceso o escena observable.',
                'harmony' => 'Explicación inicial y después cada característica en orden con Característica, Desarrollo, Pauta y Ejemplo.',
                'deficit' => 'Explicación inicial y cada característica en orden con Característica, Desarrollo, Pauta y Ejemplo.',
                'excess' => 'Explicación inicial y cada característica en orden con Característica, Desarrollo, Pauta y Ejemplo.',
                'closing' => 'Síntesis, aprendizaje, recurso, riesgo, exactamente cinco preguntas y frase central con apoyos.',
            ],
            'lista_de_comprobacion_antes_de_responder' => [
                'Cada bloque responde su propia pregunta y no anticipa el contenido de un bloque posterior.',
                'No hay párrafos equivalentes ni listas con ejemplos del mismo contexto repetido.',
                'Luna solo aborda necesidad emocional, cuidado y regulación; Ascendente solo inicio, orientación y ritmo; Descendente solo reciprocidad, acuerdos y dos subjetividades.',
                'No hay predicciones, diagnósticos, etiquetas fijas ni hechos biográficos atribuidos a la persona.',
            ],
        ];

        if ($door === 'sol') {
            $user['requisitos_de_desarrollo']['harmonization'] = 'Recorridos desde defecto y exceso usando solo características ya desarrolladas: reconocer, practicar, observar resultado y ajustar.';
        }

        if ($door === 'luna') {
            $user['continuidad']['instruccion_regente'] = 'Venus ya fue presentado en la Puerta del Sol. Utiliza aquella explicación únicamente como puente. No vuelvas a explicar Venus de forma general ni repitas su interpretación solar. Desarrolla exclusivamente qué aporta Venus a la experiencia lunar: regulación emocional, formas de cuidado, reconocimiento, expresión de necesidades, vulnerabilidad y seguridad emocional.';
            $user['requisitos_de_extension'] = $this->lunaRequirements();
            $user['formato_estados'] = [
                'regla' => 'Cada característica ocupa un string independiente.',
                'estructura' => '<ol><li><strong>Característica:</strong> ...<br><strong>Pauta:</strong> ...<br><strong>Ejemplo:</strong> ...</li></ol>',
                'prohibido' => 'No agrupar, añadir, eliminar, fusionar, renombrar ni reordenar características.',
            ];
            $user['criterios_de_continuidad'] = [
                'Cada bloque responde una pregunta diferente.',
                'La Luna permanece centrada en emoción, necesidad, seguridad, afecto, vulnerabilidad, cuidado y regulación.',
                'Venus ya presentado en el Sol solo se usa como puente hacia regulación emocional y cuidado.',
                'No se atribuyen hechos biográficos, predicciones, diagnósticos ni etiquetas deterministas.',
                'Si un párrafo no ayuda a comprender qué siento, qué necesito o cómo puedo acompañarme, debe reescribirse.',
            ];
            $user['lista_de_comprobacion_antes_de_responder'] = [
                'Todos los bloques existen y son matrices de strings.',
                'shared_intro tiene 3 strings; function 3; sign 4; house 6; ruler 6; integration 4.',
                'Harmony, deficit y excess conservan exactamente el número, contenido y orden de las características recibidas.',
                'Cada característica posee una pauta relacionada y un ejemplo diferente.',
                'El cierre contiene exactamente cinco preguntas de autoobservación.',
            ];
            $user['salida'] = [
                'tipo' => 'JSON',
                'solo_claves' => $this->blocks($door),
                'regla' => 'Devuelve exclusivamente el objeto JSON final; todos los valores de primer nivel son matrices de strings.',
            ];
        }

        if ($door === 'ascendente') {
            $user['continuidad']['regla'] = 'Utiliza las puertas anteriores solo como contexto no reutilizable. Para el Ascendente elimina cualquier dependencia de los estados de Sol y Luna: retoma una idea únicamente como puente breve si permite construir una consecuencia nueva sobre inicio, orientación, ritmo o sostenimiento.';
            $user['continuidad']['instruccion_regente'] = 'Venus ya fue presentado en la Puerta del Sol. Utiliza aquella explicación únicamente como puente. No repitas Venus de forma general ni su interpretación solar. Desarrolla exclusivamente cómo Venus en Virgo y casa 6 ayuda a comprobar recursos, ajustar condiciones y convertir una intención inicial en una práctica sostenible.';
            $user['requisitos_de_extension'] = [
                'shared_intro' => ['numero_strings' => 3, 'instruccion' => 'Presenta el Ascendente como tercera puerta y su pregunta central; no interpretes todavía signo, eje, regente ni estados.'],
                'function' => ['numero_strings' => 3, 'instruccion' => 'Desarrolla entrada, orientación, primer paso, ritmo, referencias y sostenimiento; diferencia Sol, Luna y Descendente.'],
                'sign' => ['numero_strings' => 4, 'instruccion' => 'Traduce Tauro a la función de iniciar: asentamiento, recursos, ritmo y continuidad; no describas personalidad Tauro genérica.'],
                'house' => ['numero_strings' => 6, 'instruccion' => 'Desarrolla el territorio del eje I–VII visto exclusivamente desde Casa I: condición, proceso, responsabilidad, participación, consecuencia y evolución. Casa VII solo como contrapunto breve.'],
                'ruler' => ['numero_strings' => 6, 'instruccion' => 'Explica Venus hacia la pregunta del Ascendente y cómo Virgo/casa 6 matiza recursos, ajuste, condiciones y práctica sostenible sin repetir Sol o Luna.'],
                'integration' => ['numero_strings' => 4, 'instruccion' => 'Integra Ascendente Tauro, eje I–VII y Venus en Virgo/casa 6 con consecuencias nuevas y una escena posible de inicio sostenible.'],
                'harmony' => ['numero_strings' => 7, 'instruccion' => 'Conserva exactamente las siete características recibidas, en orden, una por string HTML independiente.'],
                'deficit' => ['numero_strings' => 7, 'instruccion' => 'Conserva exactamente las siete características recibidas, en orden, una por string HTML independiente.'],
                'excess' => ['numero_strings' => 7, 'instruccion' => 'Conserva exactamente las siete características recibidas, en orden, una por string HTML independiente.'],
                'closing' => ['numero_strings' => 3, 'instruccion' => 'Cierra exclusivamente el Ascendente con síntesis, aprendizaje, recurso, riesgo, cinco preguntas y frase central con apoyos.'],
            ];
            $user['formato_estados'] = [
                'regla' => 'Cada una de las siete características de cada estado ocupa un string independiente.',
                'estructura' => '<ol><li><strong>Característica:</strong> ...<br><strong>Desarrollo:</strong> ...<br><strong>Pauta:</strong> ...<br><strong>Ejemplo:</strong> ...</li></ol>',
                'prohibido' => 'No agrupar, añadir, eliminar, fusionar, renombrar ni reordenar características.',
            ];
            $user['criterios_de_continuidad'] = [
                'El Ascendente responde cómo entro en una experiencia nueva y qué ritmo puedo sostener.',
                'house significa eje I–VII observado desde Casa I, no una explicación genérica de Casa I ni un desarrollo relacional de Casa VII.',
                'No se reutilizan las características, pautas ni ejemplos de Sol o Luna.',
                'Venus se interpreta solo como recurso de inicio, medición de recursos, ajuste y sostenimiento.',
                'No hay predicciones, diagnósticos, etiquetas deterministas ni hechos biográficos.',
            ];
            $user['lista_de_comprobacion_antes_de_responder'] = [
                'Todos los bloques existen y son matrices de strings.',
                'shared_intro tiene 3 strings; function 3; sign 4; house 6; ruler 6; integration 4.',
                'house desarrolla el eje I–VII desde Casa I y no repite rutina, trabajo u organización.',
                'Harmony, deficit y excess tienen exactamente 7 strings y conservan sus características en orden.',
                'Los ejemplos varían de contexto y el cierre contiene exactamente cinco preguntas.',
            ];
            $user['salida'] = [
                'tipo' => 'JSON',
                'solo_claves' => $this->blocks($door),
                'regla' => 'Devuelve exclusivamente el objeto JSON final; todos los valores de primer nivel son matrices de strings.',
            ];
        }

        if ($door === 'descendente') {
            $user['continuidad']['regla'] = 'Utiliza las puertas anteriores solo como contexto no reutilizable. Elimina la contaminación de sus estados y retoma una idea únicamente como puente breve si permite construir una consecuencia nueva sobre reciprocidad, confianza, acuerdos, límites o autonomía.';
            $user['continuidad']['instruccion_regente'] = 'Si Marte y Plutón ya fueron presentados, no repitas sus definiciones generales. Lee Marte como vía directa de acción y gestión del compromiso; lee Plutón como capa simbólica de transformación y poder. Relaciónalos solo para explicar qué cambia en la confianza, los acuerdos, la autonomía y la revisión del vínculo.';
            $user['requisitos_de_extension'] = [
                'shared_intro' => ['numero_strings' => 3, 'instruccion' => 'Presenta el Descendente como cuarta puerta y su pregunta central; no interpretes todavía signo, casa, regentes ni estados.'],
                'function' => ['numero_strings' => 3, 'instruccion' => 'Desarrolla encuentro entre iguales, reciprocidad, confianza, deseos, límites y acuerdos; diferencia Sol, Luna y Ascendente.'],
                'sign' => ['numero_strings' => 4, 'instruccion' => 'Traduce Escorpio a la función relacional: profundidad, privacidad, consentimiento, compromiso y autonomía; no describas una pareja predeterminada.'],
                'house' => ['numero_strings' => 6, 'instruccion' => 'Desarrolla la casa VII como territorio de dos voluntades: expectativas, negociación, responsabilidades, consentimiento, revisión y cambio temporal.'],
                'ruler' => ['numero_strings' => 8, 'instruccion' => 'Distingue Marte tradicional y Plutón moderno antes de relacionarlos; explica qué aportan a acción, confianza, acuerdos, transformación y autonomía sin repetir puertas anteriores.'],
                'integration' => ['numero_strings' => 4, 'instruccion' => 'Integra Descendente, Escorpio, casa VII, Marte y Plutón con consecuencias nuevas: qué se pregunta, qué se acuerda, qué se revisa y qué queda fuera del control propio.'],
                'harmony' => ['numero_strings' => 7, 'instruccion' => 'Conserva exactamente las siete características recibidas, en orden, una por string HTML independiente.'],
                'deficit' => ['numero_strings' => 7, 'instruccion' => 'Conserva exactamente las siete características recibidas, en orden, una por string HTML independiente.'],
                'excess' => ['numero_strings' => 7, 'instruccion' => 'Conserva exactamente las siete características recibidas, en orden, una por string HTML independiente.'],
                'closing' => ['numero_strings' => 3, 'instruccion' => 'Cierra exclusivamente el Descendente con síntesis, aprendizaje, recurso, riesgo, cinco preguntas y frase central con apoyos.'],
            ];
            $user['formato_estados'] = [
                'regla' => 'Cada una de las siete características de cada estado ocupa un string independiente.',
                'estructura' => '<ol><li><strong>Característica:</strong> ...<br><strong>Pauta:</strong> ...<br><strong>Ejemplo:</strong> ...</li></ol>',
                'prohibido' => 'No agrupar, añadir, eliminar, fusionar, renombrar ni reordenar características.',
            ];
            $user['criterios_de_continuidad'] = [
                'El Descendente responde cómo compartir la vida sin dejar de escucharse.',
                'Distingue deseo, petición, acuerdo y norma.',
                'La casa VII incluye pareja, amistad, asociación y colaboración entre iguales.',
                'No se reutilizan características, pautas ni ejemplos de Sol, Luna o Ascendente.',
                'No hay predicciones, diagnósticos, etiquetas deterministas ni hechos biográficos.',
            ];
            $user['lista_de_comprobacion_antes_de_responder'] = [
                'Todos los bloques existen y son matrices de strings.',
                'shared_intro tiene 3 strings; function 3; sign 4; house 6; ruler 8; integration 4.',
                'Ruler distingue Marte tradicional y Plutón moderno antes de integrarlos.',
                'Harmony, deficit y excess tienen exactamente 7 strings y conservan sus características en orden.',
                'Los ejemplos varían de contexto y el cierre contiene exactamente cinco preguntas.',
            ];
            $user['salida'] = [
                'tipo' => 'JSON',
                'solo_claves' => $this->blocks($door),
                'regla' => 'Devuelve exclusivamente el objeto JSON final; todos los valores de primer nivel son matrices de strings.',
            ];
        }

        return ['system' => $system, 'user' => json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    }

    /** @return list<string> */
    public function blocks(?string $door = null): array
    {
        return $door === 'sol' || $door === null ? self::SOL_BLOCKS : self::STANDARD_BLOCKS;
    }

    /** @return array<string, array{numero_strings: int, instruccion: string}> */
    private function lunaRequirements(): array
    {
        return [
            'shared_intro' => ['numero_strings' => 3, 'instruccion' => 'Presenta la Luna como segunda puerta y su pregunta central; no desarrolles todavía signo, casa, regente ni estados.'],
            'function' => ['numero_strings' => 3, 'instruccion' => 'Desarrolla experiencia emocional, necesidad, seguridad, vulnerabilidad, cuidado y regulación; diferencia Sol, Ascendente y Descendente.'],
            'sign' => ['numero_strings' => 4, 'instruccion' => 'Cada string añade una dimensión nueva de necesidad emocional, regulación, seguridad, afecto o vulnerabilidad aplicada al signo.'],
            'house' => ['numero_strings' => 6, 'instruccion' => 'Desarrolla seis perspectivas distintas de la casa: condición, proceso, responsabilidad, participación, consecuencia cotidiana y cambio temporal.'],
            'ruler' => ['numero_strings' => 6, 'instruccion' => 'Explica el regente hacia la pregunta lunar y cómo Venus en Virgo y casa 6 matiza cuidado, seguridad y regulación sin repetir su lectura solar.'],
            'integration' => ['numero_strings' => 4, 'instruccion' => 'Integra Luna, signo, casa y regente con consecuencias nuevas y una escena cotidiana formulada como posibilidad.'],
            'harmony' => ['numero_strings' => 'exactamente las características recibidas', 'instruccion' => 'Conserva orden y texto de cada característica; un string HTML independiente por característica con pauta y ejemplo distintos.'],
            'deficit' => ['numero_strings' => 'exactamente las características recibidas', 'instruccion' => 'Interpreta poco espacio emocional; un string HTML independiente por característica con pauta y ejemplo propios.'],
            'excess' => ['numero_strings' => 'exactamente las características recibidas', 'instruccion' => 'Interpreta uso rígido o desproporcionado de una capacidad lunar; un string HTML independiente por característica.'],
            'closing' => ['numero_strings' => 3, 'instruccion' => 'Cierra exclusivamente la Luna con síntesis, aprendizaje, recurso, riesgo, cinco preguntas, frase central y apoyos.'],
        ];
    }

    /** @param array<string, mixed> $context */
    private function structuredChartData(array $context): array
    {
        $degrees = (int) ($context['degrees'] ?? 0);
        $rulerDetails = $context['ruler_details'] ?? [];
        $states = $context['states'] ?? [
            'harmony' => ['characteristics' => []],
            'deficit' => ['characteristics' => []],
            'excess' => ['characteristics' => []],
        ];

        if (in_array($context['door'] ?? null, ['luna', 'descendente'], true)) {
            $rulerDetails = array_map(static fn (array $ruler): string => (string) ($ruler['name'] ?? $ruler['key'] ?? ''), $rulerDetails);
        }

        $data = [
            'name' => $context['name'] ?? null,
            'subject' => $context['subject'] ?? null,
            'sign' => $context['sign'] ?? null,
            'position' => [
                'degrees' => $degrees,
                'minutes' => (int) ($context['minutes'] ?? 0),
                'seconds' => (int) ($context['seconds'] ?? 0),
                'degree_segment' => $degrees < 10 ? 'primeros grados' : null,
            ],
            'house' => $context['house'] ?? null,
            'rulers' => $rulerDetails,
            'chart_id' => $context['chart_id'] ?? null,
        ];

        if (in_array($context['door'] ?? null, ['luna', 'descendente'], true)) {
            $data['ruler_sign'] = $context['ruler_sign'] ?? null;
            $data['ruler_house'] = $context['ruler_house'] ?? null;
            $data['ruler_degrees'] = $context['ruler_degrees'] ?? 0;
            $data['ruler_minutes'] = $context['ruler_minutes'] ?? 0;
            $data['ruler_seconds'] = $context['ruler_seconds'] ?? 0;
            $data['caracteristicas_estados'] = array_map(
                static fn (array $state): array => $state['characteristics'] ?? [],
                $states,
            );
        } else {
            $data['states'] = $states;
        }

        return $data;
    }
}
