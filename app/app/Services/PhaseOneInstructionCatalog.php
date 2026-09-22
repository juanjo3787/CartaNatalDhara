<?php

namespace App\Services;

use InvalidArgumentException;

final class PhaseOneInstructionCatalog
{
    private const SOURCES = [
        'sol' => 'docs/Instrucciones generales de desarrollo y continuidad.docx',
        'luna' => 'docs/INSTRUCCIONES_CONTINUIDAD_LUNA_FASE_1.docx',
        'ascendente' => 'docs/INSTRUCCIONES_CONTINUIDAD_ASCENDENTE_FASE_1.docx',
        'descendente' => 'docs/INSTRUCCIONES_CONTINUIDAD_DESCENDENTE_FASE_1.docx',
    ];

    /** @return array{source: string, rules: list<string>} */
    public function forDoor(string $door): array
    {
        if (! isset(self::SOURCES[$door])) {
            throw new InvalidArgumentException("Puerta Fase 1 no válida: {$door}");
        }

        $rules = [
            'sol' => [
                'Mantén el Sol centrado en identidad, voluntad, elección y dirección personal.',
                'Explica primero la función del Sol y después presenta, en secciones diferenciadas, su signo, sus grados, la casa y la posición del regente antes de integrar la lectura.',
                'No conviertas la identidad en una etiqueta fija ni confundas cooperación con renuncia al criterio propio.',
                'En sign desarrolla qué necesita el Sol en ese signo para elegir y expresarse, qué recurso aporta, qué debe aprender a distinguir y cómo se relaciona con el criterio propio; no lo conviertas en una lista de adjetivos.',
                'En house desarrolla al menos seis dimensiones distintas del territorio vital: condiciones, tareas, responsabilidades, participación, hábitos, cambios y oportunidades de aprendizaje. No enumeres asuntos de la casa sin conectarlos con voluntad y elección.',
                'En ruler explica qué vía concreta añade el regente a la identidad solar y cómo su signo y casa cambian la forma de decidir, sostener una dirección, colaborar o revisar una elección. Si ya apareció antes, no repitas sus significados generales.',
                'En integration no resumas signo, casa y regente por separado: explica las consecuencias de su combinación mediante una escena cotidiana, un proceso de decisión y un aprendizaje que no pueda deducirse de una sola pieza.',
                'Añade una armonización completa: recorrido desde el defecto para recuperar espacio, recorrido desde el exceso para recuperar medida, punto de equilibrio, pasos graduales y tres acciones observables. Armonizar no significa eliminar incomodidad ni alcanzar perfección.',
                'En harmony desarrolla primero cuatro párrafos de proceso: reconocer la necesidad, identificar una respuesta proporcionada, practicarla, observar el resultado y ajustarla. Después desarrolla individualmente cada característica con su pauta y su ejemplo.',
                'En defecto y exceso diferencia el funcionamiento y desarrolla cada característica de forma individual: una explicación propia, una pauta concreta y un ejemplo distinto. Conserva el número y el orden de las características; no las agrupes ni las sustituyas.',
                'En closing estructura el cierre en este orden: síntesis de la combinación, aprendizaje principal, recurso disponible, riesgo a vigilar, armonización desde defecto, armonización desde exceso, punto de equilibrio, cinco preguntas de autoobservación y una frase central con cuatro frases de apoyo.',
                'La profundidad es obligatoria: cada párrafo debe desarrollar una idea completa con mecanismo, matiz y consecuencia observable. Usa al menos 80 palabras en function, sign, integration y closing; al menos 90 palabras en cada párrafo de house y ruler. No reduzcas profundidad a repetir la misma idea.',
            ],
            'luna' => [
                'Mantén la Luna centrada en necesidades emocionales, seguridad, afecto, regulación, vulnerabilidad y cuidado.',
                'La pregunta específica es: “¿Qué estoy sintiendo y qué necesito en este momento?”. Si un párrafo no ayuda a responderla, debe revisarse o moverse a otra puerta.',
                'Diferencia claramente la Luna del Sol, el Ascendente y el Descendente: la Luna no trata identidad, ritmo de inicio ni negociación relacional, sino experiencia emocional y capacidad de acompañarse.',
                'Cuando el regente lunar ya apareció en el Sol, haz una referencia breve y cambia la pregunta hacia la regulación emocional, la forma de cuidado y la expresión de la necesidad.',
                'Conserva el número y el orden de las características de armonía, defecto y exceso; no sustituyas un punto por otro tema solo para evitar repetición.',
                'Cada párrafo debe aportar una función distinta: definición, matiz, contexto, proceso, consecuencia, señal de equilibrio o práctica; no uses la extensión para reformular lo mismo.',
                'No utilices identidad, voluntad o dirección personal como núcleo de la Luna, ni conviertas Libra lunar en una descripción general de personalidad.',
                'No conviertas la casa VI en seis reformulaciones de rutina, trabajo, orden o cuidado: cada párrafo debe abrir una condición, proceso, responsabilidad, participación, consecuencia o cambio temporal diferente.',
                'Los ejemplos deben variar de contexto y formularse como posibilidades observables, nunca como hechos biográficos atribuidos a la persona.',
                'Si un párrafo no ayuda a comprender qué estoy sintiendo, qué necesito o cómo puedo acompañarme emocionalmente, reescríbelo.',
            ],
            'ascendente' => [
                'Mantén el Ascendente centrado en cómo la persona entra, se orienta, se posiciona y da el primer paso.',
                'La pregunta específica es: “¿Cómo puedo dar este paso de una manera que pueda sostener?”. La puerta es sobre entrada, ritmo, referencias, atención inicial y respuesta sostenida, no sobre una imagen fija.',
                'Diferencia el Ascendente de la personalidad: no lo reduzcas a “la imagen que doy” ni a una máscara; sí describe cómo se inicia una experiencia, qué recursos se necesitan y qué ritmo permite sostener la acción.',
                'Desarrolla el eje I–VII desde la casa I; la casa VII solo puede aparecer como contrapunto breve. El Descendente será el lugar del análisis relacional profundo.',
                'Si el regente del Ascendente ya apareció antes, cambia radicalmente la pregunta: ahora interesa cómo ese planeta matiza el inicio, la medición de recursos, la respuesta a lo nuevo y la posibilidad de sostener un ritmo.',
                'Conserva la misma arquitectura de función, signo, casa, regente, integración y estados; no repitas la pedagogía general si solo hace falta introducir la diferencia específica del Ascendente.',
                'El bloque house representa el territorio del eje I–VII observado exclusivamente desde la Casa I: entrada, orientación, base propia y primer posicionamiento. La Casa VII solo es un contrapunto breve.',
                'No reutilices estados de Sol o Luna. El Ascendente debe recibir y conservar siete características propias de armonía, defecto y exceso, centradas en inicio, ritmo, recursos, referencias y sostenimiento.',
                'Venus ya presentado en el Sol se usa solo como puente: en esta puerta explica cómo ayuda a comprobar recursos, ajustar condiciones y convertir una intención inicial en una práctica sostenible.',
                'No describas a Tauro como personalidad genérica. Traduce cada rasgo a cómo la persona entra en lo nuevo, mide sus recursos, inicia y mantiene un ritmo.',
                'Los ejemplos deben variar de contexto y formularse como posibilidades observables, nunca como hechos biográficos atribuidos a la persona.',
            ],
            'descendente' => [
                'Mantén el Descendente centrado en el encuentro con otra persona, la reciprocidad, la confianza y los acuerdos.',
                'La pregunta específica es: “¿Cómo puedo compartir mi vida sin dejar de escucharme?”. El centro no es “qué persona llega”, sino qué sucede cuando hay dos voluntades, dos necesidades y decisiones que deben negociarse con igualdad.',
                'Diferencia siempre deseo, petición, acuerdo y norma; no atribuyas intenciones ni predigas qué persona llegará ni qué relaciones serán inevitables.',
                'Evita convertir el Descendente en una descripción de pareja única. La casa VII también incluye asociaciones, colaboraciones y otros vínculos de igualdad donde hay acuerdos, expectativas y responsabilidades compartidas.',
                'Cuando un tema ya apareció antes, el Descendente debe añadir la dimensión de mutualidad: qué cambia porque hay otro sujeto con su propia perspectiva, qué debe acordarse y qué no puede decidirse unilateralmente.',
                'Conserva el número y el orden de características en armonía, defecto y exceso; cada párrafo debe aportar una idea nueva y no reformular la misma relación en otras palabras.',
                'Desarrolla la casa VII como encuentro entre iguales: confianza, reciprocidad, expectativas, consentimiento, autonomía, responsabilidades compartidas y revisión de acuerdos. No la conviertas en una predicción de pareja.',
                'Distingue siempre deseo, petición, acuerdo y norma: desear no obliga, pedir permite respuesta, acordar crea responsabilidad compartida y una norma requiere aceptación real.',
                'Si Escorpio tiene regencia tradicional y moderna, diferencia Marte y Plutón antes de relacionarlos. Marte aporta una vía directa de acción y gestión; Plutón añade una capa simbólica de transformación y poder, sin convertirlo en rasgo individual exclusivo.',
                'No reutilices los estados de Sol, Luna ni Ascendente. El Descendente debe recibir siete características propias de reciprocidad, confianza, acuerdos, límites y autonomía.',
                'Los ejemplos deben variar entre pareja, amistad, colaboración, asociación, proyecto, viaje, economía no sensible y actividad grupal. Nunca atribuyas hechos biográficos a la persona.',
                'La integración debe mostrar qué cambia porque existe otra voluntad: qué se pregunta, qué se acuerda, qué se revisa y qué queda fuera del control propio.',
            ],
        ][$door];

        return ['source' => self::SOURCES[$door], 'rules' => $rules];
    }

    /** @return list<string> */
    public function general(): array
    {
        return [
            'Redacta para una persona sin conocimientos de astrología, con lenguaje cercano, claro y concreto.',
            'Explica cada término la primera vez y conecta la interpretación con experiencias comprensibles sin inventar biografía.',
            'Revisa el contenido anterior antes de redactar; cada apartado debe responder una pregunta diferente y aportar información propia.',
            'Distingue desarrollo, pauta y ejemplo. No rellenes extensiones con reformulaciones ni conclusiones repetidas.',
            'Conserva las características proporcionadas y mantén una correspondencia exacta entre característica, pauta y ejemplo.',
            'Sigue el orden función, signo, casa o eje, regente, integración, armonía, defecto y exceso.',
            'La armonía es un uso proporcionado, el defecto es falta de espacio y el exceso es una capacidad rígida o desproporcionada.',
            'Revisa continuidad, repeticiones, coherencia de datos y comprensión desde la perspectiva de quien lee el informe.',
            'El lenguaje general debe seguir siendo no determinista: se formulan posibilidades para observar, no hechos biográficos no contados por la persona.',
            'No hagas predicciones, diagnósticos ni afirmaciones biográficas; signo y casa abren preguntas, no demuestran hechos.',
            'La continuidad se mide por la profundidad añadida, no por la cantidad de veces que repites una definición; si una idea ya aparece, reutilízala solo como puente y añade matiz nuevo.',
        ];
    }
}