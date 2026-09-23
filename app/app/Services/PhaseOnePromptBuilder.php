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
        $stateFormat = 'ESTRUCTURA OBLIGATORIA DE LOS ESTADOS: Cada estado debe contener 4 capas separadas: 1) DESARROLLO INTERPRETATIVO (mínimo 3 párrafos explicando el estado, necesidad, recursos y sensaciones). 2) CABECERA "Características que puedes observar" seguida de 5-7 características breves. 3) CABECERA "Pautas y consideraciones" seguida de pautas desarrolladas para cada característica. 4) CABECERA "Ejemplos cotidianos" seguida de ejemplos narrativos desarrollados. Cada cabecera debe tener su propio contenido. NO mezclar cabeceras y contenido.';
        $system = implode("\n", [
            'Eres una redactora editorial especializada en informes de astrología simbólica con PROFUNDIDAD DE DOSSIER, no esquemas simplificados.',
            'Estas instrucciones son obligatorias. La fuente específica de esta puerta es: '.$instructions['source'],
            ...array_map(static fn (string $rule): string => '- '.$rule, $this->instructionCatalog->general()),
            ...array_map(static fn (string $rule): string => '- '.$rule, $instructions['rules']),
            'El cumplimiento se evalúa por BLOQUE COMPLETO. No des una explicación genérica de astrología ni reutilices una idea con sinónimos para alcanzar la extensión.',
            'En function explica solo la función específica de esta puerta y cómo se distingue de las otras tres; todavía no interpretes signo, casa, regente ni estados.',
            'En sign responde cómo se expresa la función de esta puerta a través del signo; no copies una interpretación del mismo signo que serviría para otra puerta.',
            'En house desarrolla seis perspectivas distintas del territorio o eje indicado. Cada párrafo debe añadir una condición, proceso, responsabilidad, participación o cambio temporal nuevo.',
            'En ruler explica primero qué aporta el regente a la pregunta de esta puerta y después cómo su signo y casa lo matizan; si ya se presentó, úsalo solo como puente y aporta una consecuencia nueva.',
            'En integration construye consecuencias que solo aparecen al reunir puerta, signo, casa o eje y regente. Incluye una escena o proceso observable, no una suma de resúmenes.',
            'ESTRUCTURA OBLIGATORIA DE HARMONY: Escribe primero 3-4 párrafos explicando la expresión armónica usando PUERTA + SIGNO + CASA + REGENTE. Después escribe exactamente "Características que puedes observar" seguido de 5-7 características breves. Después escribe exactamente "Pautas y consideraciones para reconocer este equilibrio" seguido de pautas desarrolladas. Después escribe exactamente "Ejemplos cotidianos de estas pautas" seguido de ejemplos narrativos.',
            'ESTRUCTURA OBLIGATORIA DE DEFICIT: Escribe primero 3-4 párrafos explicando la expresión por defecto usando PUERTA + SIGNO + CASA + REGENTE. Después escribe exactamente "Características que puedes observar" seguido de 7 características específicas. Después escribe exactamente "Pautas y consideraciones para empezar a armonizar" seguido de 7 pautas desarrolladas. Después escribe exactamente "Ejemplos cotidianos y formas de empezar a armonizar" seguido de 7 ejemplos narrativos.',
            'ESTRUCTURA OBLIGATORIA DE EXCESS: Escribe primero 3-4 párrafos explicando la expresión por exceso usando PUERTA + SIGNO + CASA + REGENTE. Después escribe exactamente "Características que puedes observar" seguido de 7 características. Después escribe exactamente "Pautas y consideraciones para recuperar una medida adecuada" seguido de 7 pautas desarrolladas. Después escribe exactamente "Ejemplos cotidianos y formas de recuperar medida" seguido de 7 ejemplos narrativos.',
            'ESTRUCTURA OBLIGATORIA DE HARMONIZACIÓN: Desde el defecto (2-4 párrafos específicos + puntos concretos para comenzar), Desde el exceso (2-4 párrafos específicos + puntos concretos para recuperar medida), El punto de equilibrio (varios párrafos que integren puerta + signo + casa/eje + regente + signo del regente + casa del regente), Referencias para reconocer ese equilibrio (lista de referencias específicas).',
            'ESTRUCTURA OBLIGATORIA DE CLOSING: Síntesis, aprendizaje principal, recurso, riesgo, bloque PREGUNTAS DE AUTOOBSERVACIÓN (1-2 párrafos explicativos + 5 preguntas ESPECÍFICAS de esta puerta, no genéricas), bloque FRASES DE INTEGRACIÓN (frase central + Para empezar + Para recuperar medida + Para revisar + Para reunir lo aprendido).',
            'PROFUNDIDAD OBLIGATORIA: Cada párrafo debe contener una idea desarrollada, un matiz y una consecuencia o forma de observación. Mínimo 4 párrafos desarrollados antes de cualquier lista en los estados.',
            'Regla de no repetición endurecida: antes de redactar cada bloque, identifica qué conceptos, ejemplos y regentes ya aparecen en puertas_anteriores. Solo puedes retomarlos como puente breve seguido de una consecuencia nueva; no repitas definiciones, escenas, pautas ni frases con sinónimos.',
            'Objetivo editorial obligatorio: produce un dossier desarrollado con DENSIDAD Y PROFUNDIDAD equivalente al DOSSIER DE REFERENCIA. Si el límite de tokens obliga a resumir, la prioridad es dividir la generación en más llamadas, NO reducir profundidad.',
            'DATOS ASTROLÓGICOS CANÓNICOS: Los datos proporcionados en datos_carta_canonicos son DEFINITIVOS. NO los recalcules, NO los sustituyas, NO infieras posiciones distintas, NO mezcles posiciones de otras cartas. Limítate exclusivamente a interpretarlos.',
            'PROHIBICIÓN DE TEXTO GENÉRICO: Rechazar cualquier párrafo que pudiera reutilizarse sin cambios en las cuatro puertas. Convertir principios internos en contenido específico para ESTA combinación.',
            'PROFUNDIDAD DE EJEMPLOS: Cada ejemplo debe funcionar como mini escena narrativa con contexto, respuesta, experiencia, alternativa y aprendizaje. NO aceptar ejemplos de una sola línea.',
            'PERSONALIZACIÓN: Utilizar el nombre de forma natural en momentos determinados, mantener coherencia gramatical de género durante todo el informe.',
            'Cada característica debe seguir la secuencia: nombrar, explicar, contextualizar en esta carta, traducir a experiencia cotidiana, proponer una pauta y dar un ejemplo coherente. Mantén el mismo orden entre característica, pauta y ejemplo.',
            'Si una respuesta amenaza con quedarse corta, desarrolla cada pieza y su consecuencia con más detalle; no agrupes ideas distintas en una frase ni elimine pasos prácticos.',
            'Devuelve exclusivamente un objeto JSON con las claves solicitadas y sin texto fuera del JSON.',
            'Esquema JSON obligatorio: la respuesta raíz debe ser un objeto; debe contener exactamente las claves de bloques_obligatorios; cada valor debe ser un array JSON de strings no vacíos; no uses objetos, null, claves adicionales, Markdown ni texto fuera del JSON.',
            $stateFormat,
            'En los bloques narrativos usa strings HTML con párrafos completos (<p>...</p>) o texto limpio, pero nunca mezcles JSON dentro del HTML ni HTML sin cerrar. Escapa comillas dentro del JSON y conserva caracteres Unicode válidos.',
            'No uses Markdown ni etiquetas HTML fuera de p, ol, ul, li, strong, em y br. En los estados, separa las listas de características, pautas y ejemplos; no pongas los cuatro niveles dentro del mismo li. No uses atributos, enlaces, estilos, scripts ni etiquetas sin cerrar.',
            'No introduzcas etiquetas HTML peligrosas, scripts, estilos, enlaces ni atributos; solo se permiten p, ul, ol, li, strong, em y br.',
            'CONTROL DE CALIDAD AUTOMÁTICO: Verificar que existe desarrollo narrativo antes de cada lista, que cada cabecera tiene contenido propio, que cada característica tiene pauta correspondiente, que cada pauta tiene ejemplo correspondiente, que los ejemplos no son frases de una sola línea, que no hay posiciones astrológicas contradictorias, que no se reutiliza la misma armonización, que las preguntas son específicas, que existe frase central y frases de apoyo, que el género se mantiene, que no se han inventado datos.',
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
                'harmony' => 'ESTRUCTURA DE 4 CAPAS: 1) DESARROLLO INTERPRETATIVO AMPLIO (mínimo 4 párrafos) que use PUERTA + SIGNO + CASA/EJE + REGENTE + SIGNO DEL REGENTE + CASA DEL REGENTE. 2) "Características que puedes observar" (5-7 características breves). 3) "Pautas y consideraciones para reconocer este equilibrio" (una pauta desarrollada de 40-90 palabras por característica). 4) "Ejemplos cotidianos de estas pautas" (ejemplos narrativos de 80-150 palabras cada uno).',
                'deficit' => 'ESTRUCTURA DE 4 CAPAS: 1) DESARROLLO INTERPRETATIVO AMPLIO (mínimo 4 párrafos) explicando qué capacidad tiene poco espacio, por qué ocurre, qué protege, qué alivio da, qué consecuencias genera, cómo intervienen signo/casa/regente. 2) "Características que puedes observar" (7 características específicas). 3) "Pautas y consideraciones para empezar a armonizar" (7 pautas desarrolladas, no órdenes genéricas). 4) "Ejemplos cotidianos y formas de empezar a armonizar" (7 ejemplos narrativos desarrollados).',
                'excess' => 'ESTRUCTURA DE 4 CAPAS: 1) DESARROLLO INTERPRETATIVO AMPLIO (mínimo 4 párrafos) explicando qué capacidad útil funciona, cuándo ocupa demasiado espacio, qué intenta asegurar, por qué cuesta detenerla, qué recompensa mantiene, qué coste aparece, cómo intervienen signo/casa/regente. El exceso NO es defecto moral. 2) "Características que puedes observar" (7 características). 3) "Pautas y consideraciones para recuperar una medida adecuada" (7 pautas desarrolladas). 4) "Ejemplos cotidianos y formas de recuperar medida" (7 ejemplos narrativos).',
                'closing' => 'ESTRUCTURA: Síntesis, aprendizaje principal, recurso, riesgo, bloque PREGUNTAS DE AUTOOBSERVACIÓN (1-2 párrafos explicativos + 5 preguntas ESPECÍFICAS de esta puerta), bloque FRASES DE INTEGRACIÓN (frase central + Para empezar + Para recuperar medida + Para revisar + Para reunir lo aprendido).',
            ],
            'lista_de_comprobacion_antes_de_responder' => [
                'Cada bloque responde su propia pregunta y no anticipa el contenido de un bloque posterior.',
                'No hay párrafos equivalentes ni listas con ejemplos del mismo contexto repetido.',
                'El resultado tiene profundidad de dossier y no es una síntesis ni una versión abreviada.',
                'Cada bloque usa la combinación concreta de puerta, signo, casa o eje, regente y posición del regente.',
                'Los estados están separados en desarrollo, características, pautas y ejemplos; no usan la ficha Característica/Desarrollo/Pauta/Ejemplo repetida.',
                'Luna solo aborda necesidad emocional, cuidado y regulación; Ascendente solo inicio, orientación y ritmo; Descendente solo reciprocidad, acuerdos y dos subjetividades.',
                'No hay predicciones, diagnósticos, etiquetas fijas ni hechos biográficos atribuidos a la persona.',
            ],
            'control_calidad_dossier' => [
                'No es una síntesis, resumen ni versión abreviada del dossier.',
                'Cada bloque utiliza puerta, signo, casa o eje, regente o regentes y posición del regente concretos.',
                'Cada característica importante tiene nombre, explicación, contexto de esta carta, experiencia cotidiana, pauta y ejemplo correspondiente.',
                'Armonía, defecto y exceso son desarrollos diferentes; sus ejemplos no repiten una plantilla ni un único contexto.',
                'La profundidad de Luna, Ascendente y Descendente debe igualar la profundidad del Sol.',
                'No se elimina desarrollo para ahorrar tokens; si una idea es importante, se desarrolla con mecanismo, matiz, consecuencia y acción práctica.',
                'VERIFICACIÓN DE ESTRUCTURA: Cada estado debe tener 4 capas separadas (desarrollo + características + pautas + ejemplos). Cada cabecera debe tener su propio contenido.',
                'VERIFICACIÓN DE PROFUNDIDAD: Los ejemplos deben ser mini escenas narrativas de 80-150 palabras, no frases de una sola línea.',
                'VERIFICACIÓN DE DATOS: No hay posiciones astrológicas contradictorias con los datos canónicos proporcionados.',
            ],
            'rotulos_estados_obligatorios' => [
                'harmony' => [
                    'Características que puedes observar',
                    'Pautas y consideraciones para reconocer este equilibrio',
                    'Ejemplos cotidianos de estas pautas',
                ],
                'deficit' => [
                    'Características que puedes observar',
                    'Pautas y consideraciones para empezar a armonizar',
                    'Ejemplos cotidianos y formas de empezar a armonizar',
                ],
                'excess' => [
                    'Características que puedes observar',
                    'Pautas y consideraciones para recuperar una medida adecuada',
                    'Ejemplos cotidianos y formas de recuperar medida',
                ],
            ],
        ];

        if ($door === 'sol') {
            $user['requisitos_de_desarrollo']['harmonization'] = 'Recorridos desde defecto y exceso usando solo características ya desarrolladas: reconocer, practicar, observar resultado y ajustar.';
            $user['requisitos_de_extension'] = [
                'shared_intro' => ['numero_strings' => 3, 'instruccion' => 'Devuelve exactamente 3 strings independientes, uno por párrafo. No agrupes los tres párrafos en un solo string.'],
                'function' => ['numero_strings' => 3, 'instruccion' => 'Devuelve exactamente 3 strings independientes sobre la función solar.'],
                'sign' => ['numero_strings' => 4, 'instruccion' => 'Devuelve exactamente 4 strings independientes sobre el signo aplicado al Sol.'],
                'house' => ['numero_strings' => 6, 'instruccion' => 'Devuelve exactamente 6 strings independientes sobre el territorio de la casa.'],
                'ruler' => ['numero_strings' => 6, 'instruccion' => 'Devuelve exactamente 6 strings independientes sobre el regente.'],
                'integration' => ['numero_strings' => 4, 'instruccion' => 'Devuelve exactamente 4 strings independientes de integración.'],
                'harmony' => ['numero_strings' => 7, 'instruccion' => 'Devuelve aproximadamente 7 strings HTML basados en las características. Puedes agrupar o expandir ligeramente según el desarrollo editorial.'],
                'deficit' => ['numero_strings' => 7, 'instruccion' => 'Devuelve aproximadamente 7 strings HTML basados en las características. Puedes agrupar o expandir ligeramente según el desarrollo editorial.'],
                'excess' => ['numero_strings' => 7, 'instruccion' => 'Devuelve aproximadamente 7 strings HTML basados en las características. Puedes agrupar o expandir ligeramente según el desarrollo editorial.'],
                'harmonization' => ['numero_strings' => 4, 'instruccion' => 'Devuelve exactamente 4 strings independientes de armonización.'],
                'closing' => ['numero_strings' => 3, 'instruccion' => 'Devuelve exactamente 3 strings independientes de cierre.'],
            ];
        }

        if ($door === 'luna') {
            $user['continuidad']['instruccion_regente'] = 'Venus ya fue presentado en la Puerta del Sol. Utiliza aquella explicación únicamente como puente. No vuelvas a explicar Venus de forma general ni repitas su interpretación solar. Desarrolla exclusivamente qué aporta Venus a la experiencia lunar: regulación emocional, formas de cuidado, reconocimiento, expresión de necesidades, vulnerabilidad y seguridad emocional.';
            $user['requisitos_de_extension'] = $this->lunaRequirements();
            $user['formato_estados'] = [
                'regla' => 'Las características, las pautas y los ejemplos ocupan capas separadas; no agrupes los cuatro niveles dentro de cada punto.',
                'estructura' => 'Desarrollo narrativo → Características que puedes observar → Pautas y consideraciones → Ejemplos cotidianos desarrollados.',
                'prohibido' => 'No agrupar, añadir, eliminar, fusionar, renombrar ni reordenar características.',
                'rotulos_exactos' => [
                    'harmony' => ['Características que puedes observar', 'Pautas y consideraciones para reconocer este equilibrio', 'Ejemplos cotidianos de estas pautas'],
                    'deficit' => ['Características que puedes observar', 'Pautas y consideraciones para empezar a armonizar', 'Ejemplos cotidianos y formas de empezar a armonizar'],
                    'excess' => ['Características que puedes observar', 'Pautas y consideraciones para recuperar una medida adecuada', 'Ejemplos cotidianos y formas de recuperar medida'],
                ],
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
                'Harmony, deficit y excess utilizan las características recibidas como base, permitiendo ligeros ajustes en número o agrupación según el desarrollo editorial.',
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
                'harmony' => ['numero_strings' => 7, 'instruccion' => 'Utiliza las siete características recibidas como base, permitiendo ligeros ajustes según el desarrollo editorial.'],
                'deficit' => ['numero_strings' => 7, 'instruccion' => 'Utiliza las siete características recibidas como base, permitiendo ligeros ajustes según el desarrollo editorial.'],
                'excess' => ['numero_strings' => 7, 'instruccion' => 'Utiliza las siete características recibidas como base, permitiendo ligeros ajustes según el desarrollo editorial.'],
                'closing' => ['numero_strings' => 3, 'instruccion' => 'Cierra exclusivamente el Ascendente con síntesis, aprendizaje, recurso, riesgo, cinco preguntas y frase central con apoyos.'],
            ];
            $user['formato_estados'] = [
                'regla' => 'Las características se utilizan como base; después van las pautas y después los ejemplos, manteniendo un orden coherente.',
                'estructura' => 'Desarrollo narrativo → Características que puedes observar → Pautas y consideraciones → Ejemplos cotidianos desarrollados.',
                'prohibido' => 'No eliminar completamente las características base ni cambiar radicalmente su significado.',
                'rotulos_exactos' => [
                    'harmony' => ['Características que puedes observar', 'Pautas y consideraciones para reconocer este equilibrio', 'Ejemplos cotidianos de estas pautas'],
                    'deficit' => ['Características que puedes observar', 'Pautas y consideraciones para empezar a armonizar', 'Ejemplos cotidianos y formas de empezar a armonizar'],
                    'excess' => ['Características que puedes observar', 'Pautas y consideraciones para recuperar una medida adecuada', 'Ejemplos cotidianos y formas de recuperar medida'],
                ],
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
                'Harmony, deficit y excess tienen aproximadamente 7 strings basados en las características recibidas.',
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
                'harmony' => ['numero_strings' => 7, 'instruccion' => 'Utiliza las siete características recibidas como base, permitiendo ligeros ajustes según el desarrollo editorial.'],
                'deficit' => ['numero_strings' => 7, 'instruccion' => 'Utiliza las siete características recibidas como base, permitiendo ligeros ajustes según el desarrollo editorial.'],
                'excess' => ['numero_strings' => 7, 'instruccion' => 'Utiliza las siete características recibidas como base, permitiendo ligeros ajustes según el desarrollo editorial.'],
                'closing' => ['numero_strings' => 3, 'instruccion' => 'Cierra exclusivamente el Descendente con síntesis, aprendizaje, recurso, riesgo, cinco preguntas y frase central con apoyos.'],
            ];
            $user['formato_estados'] = [
                'regla' => 'Las características se utilizan como base; después van las pautas y después los ejemplos, manteniendo un orden coherente.',
                'estructura' => 'Desarrollo narrativo → Características que puedes observar → Pautas y consideraciones → Ejemplos cotidianos desarrollados.',
                'prohibido' => 'No eliminar completamente las características base ni cambiar radicalmente su significado.',
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
                'Harmony, deficit y excess tienen aproximadamente 7 strings basados en las características recibidas.',
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
            'harmony' => ['numero_strings' => 'aproximadamente las características recibidas', 'instruccion' => 'Utiliza las características como base, permitiendo ligeros ajustes según el desarrollo editorial.'],
            'deficit' => ['numero_strings' => 'aproximadamente las características recibidas', 'instruccion' => 'Interpreta poco espacio emocional; utiliza las características como base con flexibilidad editorial.'],
            'excess' => ['numero_strings' => 'aproximadamente las características recibidas', 'instruccion' => 'Interpreta uso rígido o desproporcionado; utiliza las características como base con flexibilidad editorial.'],
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

        // DATOS ASTROLÓGICOS CANÓNICOS - IMPERATIVO PARA EVITAR RECALCULOS
        $data['datos_carta_canonicos'] = [
            'instruccion' => 'Estos datos son DEFINITIVOS. NO los recalcules, NO los sustituyas, NO infieras posiciones distintas, NO mezcles posiciones de otras cartas. Limítate exclusivamente a interpretarlos.',
            'Sol' => $context['canonical_positions']['Sol'] ?? $context['sun_position'] ?? null,
            'Luna' => $context['canonical_positions']['Luna'] ?? $context['moon_position'] ?? null,
            'Mercurio' => $context['canonical_positions']['Mercurio'] ?? $context['mercury_position'] ?? null,
            'Venus' => $context['canonical_positions']['Venus'] ?? $context['venus_position'] ?? null,
            'Marte' => $context['canonical_positions']['Marte'] ?? $context['mars_position'] ?? null,
            'Júpiter' => $context['canonical_positions']['Júpiter'] ?? $context['jupiter_position'] ?? null,
            'Saturno' => $context['canonical_positions']['Saturno'] ?? $context['saturn_position'] ?? null,
            'Urano' => $context['canonical_positions']['Urano'] ?? $context['uranus_position'] ?? null,
            'Neptuno' => $context['canonical_positions']['Neptuno'] ?? $context['neptune_position'] ?? null,
            'Plutón' => $context['canonical_positions']['Plutón'] ?? $context['pluto_position'] ?? null,
            'Ascendente' => $context['canonical_positions']['Ascendente'] ?? $context['ascendant_position'] ?? null,
            'Descendente' => $context['canonical_positions']['Descendente'] ?? $context['descendant_position'] ?? null,
            'Regente del Sol' => $context['canonical_positions']['Regente del Sol'] ?? $context['sun_ruler'] ?? null,
            'Regente de la Luna' => $context['canonical_positions']['Regente de la Luna'] ?? $context['moon_ruler'] ?? null,
            'Regente del Ascendente' => $context['canonical_positions']['Regente del Ascendente'] ?? $context['ascendant_ruler'] ?? null,
            'Regente tradicional del Descendente' => $context['canonical_positions']['Regente tradicional del Descendente'] ?? $context['descendant_traditional_ruler'] ?? null,
            'Regente moderno del Descendente' => $context['canonical_positions']['Regente moderno del Descendente'] ?? $context['descendant_modern_ruler'] ?? null,
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
