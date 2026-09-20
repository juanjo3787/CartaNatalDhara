<?php

namespace App\Services;

final class PhaseOneDoorCatalog
{
    /**
     * @param array{subject: string, sign: string, degrees: int, minutes: int, seconds: int, house: int, house_sign: string, rulers: string, ruler_sign: string, ruler_house: int, name: string} $context
     * @return array<string, list<string>>
     */
    public function blocks(string $door, array $context): array
    {
        $subject = $context['subject'];
        $name = $context['name'];
        $focus = match ($door) {
            'sol' => 'la identidad, la voluntad y la dirección personal',
            'luna' => 'las necesidades emocionales, la seguridad y las respuestas instintivas',
            'ascendente' => 'la manera de entrar en la vida, orientarse y dar el primer paso',
            'descendente' => 'la forma de vincularse, negociar necesidades y compartir decisiones',
        };
        $signProfile = $this->signProfile($context['sign']);
        $houseProfile = $this->houseProfile((int) $context['house']);
        $features = $this->stateFeatures($door, $context['sign']);
        $doorProfile = $this->doorProfile($door);

        return [
            'shared_intro' => [
                sprintf('%s, esta puerta observa %s. La carta no fija una personalidad ni predice acontecimientos: ofrece un lenguaje simbólico para reconocer tendencias y posibilidades de expresión.', $name, $focus),
                sprintf('La pregunta de esta sección se responde poniendo en relación el punto natal, su signo, la casa donde se encuentra y el planeta que rige ese signo. Cada elemento aporta una parte del mapa y ninguno debe leerse de forma aislada.', $name),
                'La utilidad de la lectura está en convertir el símbolo en observación concreta: qué ocurre, qué necesidad intenta cuidar y qué respuesta diferente podría ensayarse.',
            ],
            'function' => [
                ...($door === 'sol' ? $this->solFunction($context) : ($door === 'luna' ? $this->lunaFunction($context) : ($door === 'ascendente' ? $this->ascendenteFunction($context) : ($door === 'descendente' ? $this->descendenteFunction($context) : [
                    $doorProfile['function'][0],
                    $doorProfile['function'][1],
                    $doorProfile['function'][2],
                    sprintf('En esta carta, %s está en %s a %d° %d\' %02d\"%s. La posición se propone como una invitación a contrastar cómo vives esta función, no como una descripción cerrada.', $subject, $context['sign'], $context['degrees'], $context['minutes'], $context['seconds'], $context['house'] ? sprintf(' y se expresa en casa %d', $context['house']) : ''),
                ])))),
            ],
            'sign' => [
                ...($door === 'sol' && $context['sign'] === 'Libra' && (int) $context['house'] === 6
                    ? $this->solLibraSixSign($context)
                    : ($door === 'luna' && $context['sign'] === 'Leo' && (int) $context['house'] === 4
                        ? $this->lunaLeoFourSign($context)
                    : ($door === 'ascendente' && $context['sign'] === 'Tauro'
                        ? $this->ascendenteTauroSign()
                    : ($door === 'descendente' && $context['sign'] === 'Escorpio'
                        ? $this->descendenteEscorpioSign()
                    : [
                        sprintf('%s busca expresarse en %s mediante %s.', $subject, $context['sign'], $signProfile['needs']),
                        $signProfile['development'],
                        $doorProfile['sign_transition'],
                        $signProfile['example'],
                        sprintf('Necesidades que pueden observarse: %s.', $signProfile['needs']),
                    ])))),
            ],
            'house' => [
                ...($door === 'sol' && (int) $context['house'] === 6
                    ? $this->solHouseSix($context)
                    : ($door === 'luna' && (int) $context['house'] === 4
                        ? $this->lunaHouseFour($context)
                        : ($door === 'ascendente' && (int) $context['house'] === 1
                            ? $this->ascendenteHouseOne($context)
                        : [
                            sprintf('La posición está en la casa %d, cuya cúspide se encuentra en %s.', $context['house'], $context['house_sign']),
                            $houseProfile['meaning'],
                            $houseProfile['experiences'],
                            $houseProfile['needs'],
                            $houseProfile['example'],
                            'La pregunta práctica es: ¿cómo puedo participar en este territorio sin reducir toda mi identidad a él? El equilibrio aparece cuando la experiencia se convierte en aprendizaje y no en una exigencia permanente.',
                        ]))),
            ],
            'ruler' => [
                ...($door === 'sol' && $context['sign'] === 'Libra' && (int) $context['ruler_house'] === 6
                    ? $this->solVenusRuler($context)
                    : ($door === 'luna' && $context['sign'] === 'Leo'
                        ? $this->lunaSolRuler($context)
                    : ($door === 'ascendente' && $context['sign'] === 'Tauro'
                        ? $this->ascendantVenusRuler($context)
                    : ($door === 'descendente' && $context['sign'] === 'Escorpio'
                        ? $this->descendantRulers($context)
                        : [
                            sprintf('El signo de %s está regido por %s. El regente aporta información sobre el canal mediante el que esta función busca desarrollarse.', $subject, $context['rulers']),
                            sprintf('El regente se encuentra en %s y su posición se vincula con la casa %d. Esto añade una segunda escena: la función no solo necesita expresarse en su propia casa, también recibe recursos, límites o preguntas desde ese otro territorio.', $context['ruler_sign'], $context['ruler_house']),
                            'Si existen dos regentes, se pueden leer como dos vías complementarias: una más directa y concreta y otra más profunda, transformadora o generacional. No significa que haya dos destinos, sino dos lenguajes para observar la misma necesidad.',
                            'La práctica consiste en comprobar qué conducta facilita el regente, cuál puede exagerarse y qué decisión pequeña permite utilizarlo como recurso sin convertirlo en una explicación total de la persona.',
                        ])))),
            ],
            'integration' => [
                sprintf('La integración reúne %s, el signo %s, la casa %d y el regente %s.', $subject, $context['sign'], $context['house'], $context['rulers']),
                sprintf('El signo aporta la cualidad, la casa muestra dónde se practica y el regente indica por qué vía puede organizarse. Juntos forman un patrón particular que no se reduce a una frase sobre el signo.', $subject),
                sprintf('Un ejemplo cotidiano podría aparecer cuando una situación relacionada con la casa %d activa la necesidad de %s. La respuesta más útil no sería eliminar la necesidad, sino darle una forma consciente y proporcionada.', $context['house'], $focus),
                'El recurso principal es poder reconocer el patrón antes de que se vuelva automático. El conflicto aparece cuando se confunde una forma aprendida de protegerse con la única forma posible de vivir la experiencia.',
            ],
            'harmony' => [
                ...($door === 'sol' && $context['sign'] === 'Libra' && (int) $context['house'] === 6
                    ? $this->solHarmonyLibraSix()
                    : ($door === 'luna' && $context['sign'] === 'Leo' && (int) $context['house'] === 4
                        ? $this->lunaHarmonyLeoFour()
                    : [
                        sprintf('En una expresión armónica, %s encuentra una medida adecuada: tiene espacio suficiente para participar sin ocuparlo todo.', $subject),
                        'Se puede reconocer cuando la persona identifica su necesidad principal, elige una respuesta proporcionada y puede modificarla al observar sus resultados.',
                        'La práctica es concreta: detectar la señal, nombrar lo que se necesita, responder con una acción posible y revisar después qué cambió.',
                        'La armonía no significa ausencia de dificultad. Significa disponer de margen para escuchar la función y elegir cómo expresarla sin juzgarse por necesitarla.',
                        ])),
            ],
            'deficit' => [
                ...($door === 'sol' && $context['sign'] === 'Libra' && (int) $context['house'] === 6
                    ? $this->solDeficitLibraSix()
                    : ($door === 'luna' && $context['sign'] === 'Leo' && (int) $context['house'] === 4
                        ? $this->lunaDeficitLeoFour()
                    : [
                        sprintf('Por defecto, %s puede tener poco espacio. Pueden aparecer evitación, inseguridad, desconexión o dificultad para pedir lo que se necesita.', $subject),
                        'El bloqueo suele intentar proteger de un riesgo percibido: equivocarse, exponerse, depender de alguien o perder control. Reconocer esa protección permite dejar de confundirla con falta de capacidad.',
                        'La pauta consiste en empezar por una expresión pequeña, concreta y segura. No se trata de forzar un cambio completo, sino de crear una experiencia nueva que pueda sostenerse.',
                        'El ejemplo observable sería elegir una acción mínima relacionada con la casa y registrar qué temor apareció, qué apoyo ayudó y qué resultado real se obtuvo.',
                    ])),
                implode(' ', array_map(fn (string $feature): string => $feature . '.', $features['deficit'])),
            ],
            'excess' => [
                ...($door === 'sol' && $context['sign'] === 'Libra' && (int) $context['house'] === 6
                    ? $this->solExcessLibraSix()
                    : ($door === 'luna' && $context['sign'] === 'Leo' && (int) $context['house'] === 4
                        ? $this->lunaExcessLeoFour()
                    : [
                        sprintf('Por exceso, %s puede ocupar más espacio del necesario. La persona puede anticipar, controlar, insistir o repetir una respuesta conocida para cubrir una necesidad legítima.', $subject),
                        'El exceso no es un defecto moral: suele ser una estrategia que en algún momento ofreció seguridad. El problema aparece cuando continúa aunque ya no responda a la situación presente.',
                        'La pauta es detectar la señal corporal o emocional, identificar la necesidad que intenta cubrir y reducir un grado la respuesta automática antes de actuar.',
                        'El ejemplo práctico sería dejar una parte de la situación abierta, pedir información en vez de suponerla y revisar si la relación o la tarea mejora cuando la respuesta tiene más medida.',
                    ])),
                implode(' ', array_map(fn (string $feature): string => $feature . '.', $features['excess'])),
            ],
            'closing' => [
                sprintf('El punto de equilibrio de %s no consiste en expresarlo siempre del mismo modo, sino en poder escucharlo y elegir.', $subject),
                sprintf('El aprendizaje principal es reconocer cómo %s se relaciona con la casa %d y qué recurso aporta %s.', $focus, $context['house'], $context['rulers']),
                'Preguntas de autoobservación: ¿cuándo aparece esta energía?, ¿qué necesita realmente?, ¿cuándo la expreso por defecto o por exceso?, ¿qué pequeño cambio puedo practicar esta semana?',
                sprintf('Frase de integración: «Puedo reconocer %s sin quedar definido por una sola respuesta».', $focus),
            ],
        ];
    }

    private function signProfile(string $sign): array
    {
        return [
            'Aries' => ['needs' => 'iniciativa, autonomía y una dirección clara', 'development' => 'Aries aporta impulso y capacidad de comenzar. Su aprendizaje consiste en actuar sin confundir rapidez con claridad y en comprobar cómo afecta la iniciativa a las demás personas.', 'example' => 'Una situación cotidiana puede mostrarlo cuando aparece una propuesta nueva: puedes notar el deseo de actuar y, antes de comprometerte, comprobar qué paso inicial es realmente sostenible.'],
            'Tauro' => ['needs' => 'tiempo, referencias concretas, continuidad y disfrute sencillo', 'development' => 'Tauro se relaciona con lo tangible y con la capacidad de sostener. La estabilidad resulta útil cuando sirve a una necesidad actual; necesita revisión cuando la familiaridad se convierte en una regla inamovible.', 'example' => 'Puedes observarlo al iniciar un cambio: comprobar recursos, definir una fase de prueba y revisar después permite construir confianza sin exigir certeza completa.'],
            'Géminis' => ['needs' => 'curiosidad, intercambio, movimiento mental y libertad para preguntar', 'development' => 'Géminis abre alternativas y conecta información. Su reto es distinguir explorar para comprender de acumular opciones para no decidir nunca.', 'example' => 'En una conversación puedes escuchar varias perspectivas, formular una pregunta concreta y después elegir qué información es suficiente para el siguiente paso.'],
            'Cáncer' => ['needs' => 'protección, pertenencia, intimidad y un ritmo emocional seguro', 'development' => 'Cáncer atiende lo cercano y lo que necesita cuidado. Su sensibilidad no obliga a retirarse: puede convertirse en una forma precisa de reconocer condiciones y pedir apoyo.', 'example' => 'Cuando una situación cambia, puedes identificar qué referencia quieres conservar y comunicarla sin suponer que la otra persona sabe cómo cuidarte.'],
            'Leo' => ['needs' => 'calidez, reconocimiento, expresión personal y permiso para disfrutar', 'development' => 'Leo busca dar presencia a aquello que tiene valor emocional. El reconocimiento acompaña la experiencia, pero no crea por completo el valor propio; expresarse no exige actuar para recibir aprobación.', 'example' => 'Puedes compartir una ilusión o una herida con una frase sencilla y pedir el gesto concreto que te ayudaría, sin convertir la respuesta en una prueba total del vínculo.'],
            'Virgo' => ['needs' => 'claridad, utilidad, mejora concreta y condiciones ordenadas', 'development' => 'Virgo observa detalles y encuentra formas de mejorar. El recurso se vuelve excesivo cuando intenta corregirlo todo o cuando confunde valor personal con rendimiento constante.', 'example' => 'Ante una tarea, puedes distinguir qué condición falta, qué parte depende de ti y cuándo el resultado ya es suficientemente bueno.'],
            'Libra' => ['needs' => 'reciprocidad, diálogo, buen trato y espacio para el criterio propio', 'development' => 'Libra considera distintos puntos de vista y busca acuerdos. Escuchar puede enriquecer la elección, pero el aprendizaje consiste en decidir aunque no exista unanimidad.', 'example' => 'En una colaboración puedes expresar tu preferencia, escuchar la propuesta ajena y construir un acuerdo que también incluya tu tiempo.'],
            'Escorpio' => ['needs' => 'honestidad, profundidad, privacidad, compromiso y autonomía', 'development' => 'Escorpio busca contenido real detrás de la forma. La confianza se construye con tiempo, consentimiento y hechos; profundidad no significa acceso total ni control sobre el otro.', 'example' => 'En un vínculo puedes compartir algo por etapas, observar cómo se recibe y mantener el derecho a decir que todavía no quieres hablar de una parte.'],
            'Sagitario' => ['needs' => 'sentido, amplitud, aprendizaje y libertad de criterio', 'development' => 'Sagitario conecta experiencias con una visión más amplia. Su reto es mantener la búsqueda abierta sin convertir una convicción personal en una norma para todas las personas.', 'example' => 'Al aprender algo puedes sostener una dirección y revisar el plan cuando aparecen datos nuevos, sin perder el propósito.'],
            'Capricornio' => ['needs' => 'estructura, responsabilidad, metas y resultados sostenibles', 'development' => 'Capricornio organiza el esfuerzo y atiende las consecuencias. La responsabilidad puede ser un recurso cuando admite límites y revisión; se vuelve pesada cuando obliga a soportarlo todo.', 'example' => 'En un proyecto puedes definir etapas, responsabilidades y una fecha de revisión para que el compromiso sea realista.'],
            'Acuario' => ['needs' => 'autonomía, innovación, perspectiva y participación en lo colectivo', 'development' => 'Acuario observa sistemas y busca alternativas. Puede aportar independencia y visión, cuidando que la distancia mental no sustituya la conversación emocional.', 'example' => 'Ante una norma puedes proponer una alternativa, explicar su finalidad y comprobar si el grupo puede adoptarla mediante acuerdo.'],
            'Piscis' => ['needs' => 'sensibilidad, imaginación, conexión y límites cuidadosos', 'development' => 'Piscis percibe matices y significados sutiles. Su aprendizaje es conservar la sensibilidad sin absorberlo todo ni sustituir los hechos por expectativas.', 'example' => 'En una relación puedes validar lo que intuyes, preguntar qué ocurre realmente y decidir qué ayuda puedes ofrecer sin perder tus propios límites.'],
        ][$sign] ?? ['needs' => 'reconocimiento y una forma flexible de expresión', 'development' => 'El signo aporta una cualidad que puede explorarse como recurso y revisarse cuando se vuelve rígida.', 'example' => 'Observa una situación concreta y comprueba qué respuesta te permite participar con más libertad.'];
    }

    private function doorProfile(string $door): array
    {
        return match ($door) {
            'sol' => [
                'function' => [
                    'El Sol representa la identidad que vas construyendo a través de tus elecciones y la voluntad que convierte un interés en una dirección que puedes desarrollar.',
                    'Puede reconocerse cuando una posibilidad te importa personalmente y necesitas decidir qué lugar darle, aunque todavía estés aprendiendo cómo llevarla a la práctica.',
                    'Esta puerta no explica por sí sola toda tu experiencia: una elección puede ilusionarte, activar emociones, necesitar un ritmo concreto o afectar a una relación. La pregunta solar es qué quieres hacer tuyo.',
                ],
                'sign_transition' => 'Aquí observaremos cómo la cualidad del signo modifica tus elecciones y qué necesitas distinguir para formar criterio propio.',
            ],
            'luna' => [
                'function' => [
                    'La Luna simboliza las necesidades emocionales, las respuestas espontáneas y las condiciones que te ayudan a recuperar suficiente seguridad para acompañarte.',
                    'Una emoción puede aparecer antes de que tengas una explicación completa. Puedes notar una contracción, una alegría o una necesidad de acercarte y decidir después qué respuesta te cuida mejor.',
                    'Esta puerta no pregunta primero qué quieres hacer, sino qué estás sintiendo, qué señal te informa de ello y qué forma de cuidado puede atenderlo sin convertir la emoción en una orden automática.',
                ],
                'sign_transition' => 'En esta puerta el signo se lee como una forma de buscar seguridad, expresar afecto y reconocer qué necesitas antes de interpretar lo ocurrido.',
            ],
            'ascendente' => [
                'function' => [
                    'El Ascendente es el punto del zodiaco que se elevaba por el horizonte oriental al nacer. En esta lectura simboliza cómo entras en una experiencia, te orientas y das una primera respuesta.',
                    'Puede reconocerse al comienzo de una situación nueva: qué miras primero, qué necesitas comprobar, qué impulso aparece y cómo lo conviertes en un paso concreto que puedas sostener.',
                    'También invita a observar ritmo, atención y señales corporales sin convertirlas en diagnósticos. Una pausa, una aceleración o la necesidad de referencias pueden informar de cómo estás entrando.',
                ],
                'sign_transition' => 'Aquí el signo se interpreta como una estrategia de entrada: qué referencias necesitas para pasar de lo desconocido a una participación más consciente.',
            ],
            default => [
                'function' => [
                    'El Descendente es el punto opuesto al Ascendente y marca la cúspide de la casa VII. Simboliza el encuentro entre dos personas que conservan voluntad, necesidades y capacidad de decisión.',
                    'Puede observarse en expectativas, confianza, reciprocidad, deseos, límites y responsabilidades que deben poder hablarse sin que una persona desaparezca ni decida por la otra.',
                    'Esta puerta no pregunta primero qué quieres, qué sientes o cómo empiezas, sino qué ocurre cuando todo ello debe convivir con la perspectiva y la libertad de otra persona.',
                ],
                'sign_transition' => 'En esta puerta el signo se lee en clave relacional: qué condiciones favorecen confianza, compromiso, privacidad y acuerdos entre dos.',
            ],
        };
    }

    private function solFunction(array $context): array
    {
        $name = $context['name'];
        $position = sprintf('%d° %d′ %d″ de %s, en casa %d', $context['degrees'], $context['minutes'], $context['seconds'], $context['sign'], $context['house']);

        return [
            sprintf('%s, el Sol representa, dentro del lenguaje simbólico de la astrología, la identidad que vas construyendo a través de tus elecciones. Para acercarte a esta idea, piensa en aquello que deseas que tenga un lugar en tu vida porque te importa personalmente: una inquietud que quieres explorar, una convicción que orienta tus decisiones o una forma de vivir que te gustaría desarrollar. Reconocerlo puede llevar tiempo. A veces descubres lo que quieres al probar algo; otras, al darte cuenta de que un camino que parecía adecuado ya no responde a ti. El Sol nos ayuda a poner palabras a esa búsqueda: «¿Qué quiero hacer mío y hacia dónde deseo avanzar?».', $name),
            'Esta función también habla de la voluntad: el paso que va desde reconocer un deseo hasta darle una oportunidad real. Puedes sentir interés por muchas posibilidades, pero elegir una implica dedicarle atención y aceptar que todavía tienes cosas que aprender. Imagina que hay un tema que siempre despierta tu curiosidad y decides empezar a estudiarlo. Esa primera decisión te permite descubrir si quieres seguir, qué dificultades estás dispuesta a atravesar y qué significado tiene para ti ese esfuerzo. La confianza en tu dirección puede crecer durante el recorrido, sin que necesites tener certeza de todo antes de comenzar.',
            'El Sol, sin embargo, no explica por sí solo quién eres. Una decisión puede ilusionarte y, al mismo tiempo, despertar miedo, requerir un ritmo más lento o afectar a una relación importante. Esas respuestas también forman parte de tu experiencia y merecen ser escuchadas. Desarrollar tu identidad supone aprender a reconocer tu intención entre todas ellas y encontrar una manera de llevarla adelante que tenga en cuenta tu realidad. Así vas distinguiendo qué deseas mantener, qué puedes adaptar y qué necesitas reconsiderar a medida que te conoces.',
            sprintf('Tu Sol en %s en casa %d. Tu Sol se encuentra a %s. La posición se presenta como una invitación a contrastar cómo se expresa tu voluntad en tu experiencia.', $context['sign'], $context['house'], $position),
        ];
    }

    private function lunaFunction(array $context): array
    {
        $name = $context['name'];
        $position = sprintf('%d° %d′ %d″ de %s, en casa %d', $context['degrees'], $context['minutes'], $context['seconds'], $context['sign'], $context['house']);

        return [
            'La Luna simboliza necesidades emocionales, respuestas espontáneas y formas de buscar cuidado cuando algo te afecta. Su pregunta no es qué deberías sentir, sino qué necesitas reconocer para acompañarte con más honestidad.',
            'Una emoción puede aparecer antes de que tengas una explicación completa. Primero notas una contracción, una alegría que quiere salir, una incomodidad o unas ganas de acercarte o retirarte; después empiezas a entender qué ha significado para ti lo ocurrido. Sentir no obliga a actuar de inmediato, pero sí aporta información.',
            'La Luna también ayuda a hablar de regulación emocional: las condiciones que permiten recuperar suficiente seguridad para pensar, descansar, pedir apoyo o continuar. Regular no significa dejar de sentir, sino encontrar una respuesta que cuide lo que está ocurriendo.',
            sprintf('%s, tu Luna se encuentra a %s. Esta posición invita a observar qué necesitas reconocer para sentirte acompañada y qué forma de cuidado te ayuda a recuperar seguridad.', $name, $position),
        ];
    }

    private function ascendenteFunction(array $context): array
    {
        $position = sprintf('%d° %d′ %d″ de %s', $context['degrees'], $context['minutes'], $context['seconds'], $context['sign']);

        return [
            'El Ascendente es el punto del zodiaco que se elevaba por el horizonte oriental al nacer. En astrología simboliza una manera de entrar en la experiencia: cómo empiezas, te orientas y respondes a lo nuevo. Su lectura va más allá de la imagen que das.',
            'Esta función se reconoce especialmente en los primeros momentos de una experiencia: cuando todavía no sabes del todo qué ocurrirá y necesitas orientarte. Preparar, observar, preguntar, probar o esperar un poco son maneras distintas de construir un puente entre lo desconocido y una participación más consciente.',
            'También conviene distinguir reacción y respuesta. Una reacción puede aparecer automáticamente —acelerarte, tensarte o querer asegurar algo— y después puedes decidir si esa estrategia sirve en esta situación. El componente corporal y atencional se observa como ritmo y señal, no como diagnóstico.',
            sprintf('Tu Ascendente está a %s, y marca la cúspide de la casa I. Esta puerta invita a observar cómo construyes una base propia antes de implicarte y cómo permites que la confianza crezca mediante la práctica.', $position),
        ];
    }

    private function descendenteFunction(array $context): array
    {
        $position = sprintf('%d° %d′ %d″ de %s', $context['degrees'], $context['minutes'], $context['seconds'], $context['sign']);

        return [
            'El Descendente es el punto opuesto al Ascendente y marca la cúspide de la casa VII. Simboliza el encuentro de igual a igual: pareja, asociaciones y vínculos donde se negocian compromisos. Permite observar qué buscas en el otro y qué capacidades puedes desarrollar también en ti.',
            'La función del Descendente empieza cuando otra persona tiene una voluntad que no controlas. Puedes saber qué deseas y, aun así, necesitar escuchar qué quiere el otro, qué puede ofrecer y qué límites tiene. El vínculo introduce diferencia, negociación y la posibilidad de descubrir aspectos de ti que solo se vuelven visibles cuando una decisión deja de depender únicamente de tu criterio.',
            'Esta puerta no habla solo de pareja. Puede observarse en una amistad, una asociación, una colaboración o cualquier relación donde los acuerdos importen. Su pregunta central es cómo construir una relación entre dos centros de decisión: expresar lo propio, conocer lo ajeno, acordar lo compartido y conservar aquello que cada persona decide por sí misma.',
            sprintf('Tu Descendente está a %s. Esta posición propone preguntas sobre confianza, intimidad, honestidad y poder de decisión compartido. No determina qué personas llegarán a tu vida ni convierte la intensidad en una obligación.', $position),
        ];
    }

    private function lunaLeoFourSign(array $context): array
    {
        return [
            '<ul><li>Calidez y afecto reconocible.</li><li>Espacio para la alegría y la expresión personal.</li><li>Reconocimiento de lo que tiene significado para ti.</li><li>Dignidad al mostrar una necesidad.</li><li>Permiso para jugar, crear y disfrutar sin rendimiento.</li></ul>',
            'Leo es un signo de fuego y de modalidad fija. El fuego se relaciona simbólicamente con vitalidad, expresión y entusiasmo; lo fijo, con sostener. En la Luna, este lenguaje puede apuntar al deseo de sentir que tu presencia importa y que puedes compartir lo que te alegra o te duele con cierta confianza.',
            'No equivale a necesitar atención constante ni a tener que ser extrovertida. Puede expresarse de forma muy privada: querer que alguien escuche una ilusión, recuerde lo que era importante o reciba con cariño una parte vulnerable. La pregunta es cómo dar espacio a esa necesidad sin dejar todo tu valor a cargo de la respuesta ajena.',
            'En una Luna, el fuego de Leo no se refiere únicamente a mostrarse hacia fuera. Puede describir una necesidad de sentir vida dentro de la experiencia emocional: notar que hay lugar para entusiasmarte, conmoverte, crear, reír y compartir aquello que para ti tiene un brillo especial.',
            'La modalidad fija añade continuidad. Cuando algo te importa emocionalmente, puede necesitar tiempo para asentarse y también para ser reconocido. Una palabra cariñosa o una reparación no siempre cambia de inmediato cómo te has quedado; quizá necesites comprobar que el gesto se sostiene.',
            'El reconocimiento conviene distinguirlo de la aprobación. Que alguien reconozca una experiencia significa que percibe que para ti ha tenido importancia, aunque no la viva igual. Esta distinción permite que el afecto sea un encuentro entre dos experiencias diferentes y no una exigencia de sentir lo mismo.',
            'También aparece la dignidad al mostrar una necesidad. Pedir cercanía puede hacerte sentir expuesta porque revela que la respuesta del otro importa. La propuesta lunar es poder comunicar qué te ayudaría y conservar tu propia referencia si la respuesta no coincide exactamente con lo esperado.',
        ];
    }

    private function ascendenteTauroSign(): array
    {
        return [
            '<ul><li>Tiempo y referencias concretas.</li><li>Constancia y capacidad de sostener.</li><li>Reconocimiento de recursos y preferencias.</li><li>Espacio para el disfrute sencillo.</li><li>Flexibilidad para actualizar lo que ya no funciona.</li></ul>',
            'Tauro es un signo de tierra y de modalidad fija. La tierra se relaciona con lo tangible, los recursos y lo que puede comprobarse; lo fijo, con la continuidad. Al empezar, puedes explorar qué condiciones te ayudan a sentir que sabes dónde pisas.',
            'Necesitar tiempo no equivale a ser incapaz de decidir. La prudencia resulta útil si permite conocer y elegir. Necesita revisión cuando la seguridad completa se convierte en requisito para probar algo que solo puede conocerse desde la experiencia.',
            'La tierra de Tauro orienta la atención hacia lo que puede sostenerse de manera concreta: tiempo disponible, energía, recursos, comodidad básica y condiciones materiales. Aplicado al Ascendente, puede ayudarte a empezar preguntando «¿con qué cuento?» antes de comprometerte.',
            'La modalidad fija añade capacidad de continuidad. Una vez que una experiencia ha encontrado su lugar, puedes aprender mucho sosteniéndola durante un tiempo suficiente. El aprendizaje consiste en distinguir continuidad de inercia: mantener algo porque sigue funcionando no es lo mismo que conservarlo únicamente porque ya existe.',
            'Tauro también está vinculado simbólicamente con el valor y el disfrute. El placer sencillo puede actuar como información sobre la relación que estableces con una experiencia. No todo lo valioso tiene que ser cómodo, pero una vida construida solo desde la obligación pierde una referencia importante.',
            'Necesitar referencias no equivale a resistirse al cambio. Una base suficiente puede facilitar que pruebes algo nuevo porque sabes qué conservarás mientras exploras. La flexibilidad taurina no consiste en moverse deprisa, sino en permitir que lo estable se actualice cuando la experiencia aporta información nueva.',
        ];
    }

    private function descendenteEscorpioSign(): array
    {
        return [
            '<ul><li>Honestidad emocional y coherencia.</li><li>Compromiso que se sostenga en hechos.</li><li>Respeto por la intimidad y la privacidad.</li><li>Capacidad de hablar de lo importante.</li><li>Autonomía dentro de una relación significativa.</li></ul>',
            'Escorpio es un signo de agua y de modalidad fija. El agua se asocia con lo emocional y lo íntimo; lo fijo, con profundidad y continuidad. En el vínculo, puede simbolizar el deseo de que lo importante no se quede en una forma correcta sin contenido.',
            'La profundidad se construye con tiempo, consentimiento y coherencia. Puedes querer conocer a alguien y respetar que conserve una parte privada. Puedes compartir vulnerabilidad y seguir decidiendo cuánto deseas ofrecer. Confiar no exige saberlo todo ni ignorar lo que los hechos muestran.',
            'El agua de Escorpio dirige la atención hacia el significado emocional de los intercambios. En el Descendente, puede hacer importante saber si una conversación es sincera, si un compromiso tiene contenido real o si existe espacio para hablar de aquello que resulta incómodo.',
            'La modalidad fija aporta capacidad de sostener y profundizar. La confianza puede crecer cuando compruebas continuidad: una persona mantiene lo acordado, reconoce un error, respeta una confidencia o conserva el interés aunque haya diferencias. Permanecer no es siempre la medida del compromiso; a veces lo es poder renegociar con honestidad.',
            'Escorpio también invita a distinguir intimidad de acceso total. Compartir algo vulnerable es una elección y puede hacerse por etapas. La profundidad relacional aumenta cuando el consentimiento forma parte de la confianza, porque demuestra que la cercanía no depende de invadir límites.',
            'Otra distinción importante es compromiso frente a control. Puedes necesitar coherencia y hechos que sostengan una relación sin convertir esa necesidad en supervisión permanente. Parte del aprendizaje consiste en observar, preguntar y decidir qué haces tú con la información disponible.',
        ];
    }

    private function ascendenteHouseOne(array $context): array
    {
        return [
            'La casa I representa tu posición de partida y la manera de entrar en las experiencias. Antes de comprometerte, puede ser útil saber qué deseas conservar: un horario, una actividad, un recurso, un tiempo de descanso o simplemente el derecho a pensar antes de responder.',
            'La primera decisión no siempre es grande. También puede aparecer al aceptar una propuesta, elegir un ritmo, probar una actividad o decidir cuánto tiempo quieres dedicar. Observar ese primer movimiento permite distinguir una elección propia de una respuesta automática a la prisa del entorno.',
            'La base personal no es una barrera frente al vínculo. Saber qué puedes sostener facilita que el encuentro con otra persona sea una adaptación elegida y no una desaparición de tus referencias. La casa VII aparece como contrapunto: toda situación compartida introduce otra voluntad.',
            'Una experiencia nueva no necesita recibir desde el principio el mismo nivel de tiempo, confianza o recursos que una ya conocida. Puedes empezar por una fase limitada, observar cómo funciona y ampliar después. Así la confianza se construye mediante hechos.',
            'El encuentro con otras personas también puede aportar información para actualizar una costumbre o una estrategia de inicio. Cambiar un método no implica abandonar el propósito; puede ser la forma de conservar lo importante en condiciones nuevas.',
            'Cuando aparezca resistencia, la pregunta útil no es por qué te cuesta cambiar de manera general, sino qué estás intentando proteger. Identificar la función de lo que conservas permite decidir si necesitas mantenerlo, transformarlo o encontrar otra forma de cuidar la misma necesidad.',
            '<ul><li>Conocer tu disponibilidad antes de comprometerte.</li><li>Conservar actividades y recursos propios.</li><li>Permitir cambios graduales.</li><li>Distinguir una preferencia estable de una resistencia automática.</li></ul>',
        ];
    }

    private function solHarmonyLibraSix(): array
    {
        return [
            'Pastora, puedes empezar a reconocer la armonía de esta combinación en la sensación que acompaña a tus decisiones. Después de considerar una situación y elegir cómo participar, te resulta posible continuar sin reabrir constantemente la elección. Tu Sol en Libra ha dado espacio a las distintas posiciones, la casa VI te permite concretar lo decidido y Venus en Escorpio aporta implicación en aquello que tiene significado para ti.',
            'Otra expresión de armonía puede aparecer como concentración y satisfacción durante una actividad. Hay momentos en los que te interesa tanto lo que estás haciendo que dejas de preguntarte cómo será valorado. Disfrutas encontrando una solución, dando forma a una idea o comprendiendo algo que antes te resultaba difícil.',
            'En el encuentro con otras personas, el equilibrio también puede reconocerse por la naturalidad con la que ocupas tu lugar. Puedes participar sin tener que destacar continuamente ni restar importancia a lo que aportas. Hay espacio para hablar, escuchar y permitir que otra persona tome la iniciativa.',
            'Por último, observa qué sucede cuando una actividad termina. En armonía, puedes recoger lo vivido y pasar a otra cosa sin que el resultado siga reclamándote por dentro. Has participado en algo significativo y todavía queda espacio para disfrutar de otras partes de tu vida.',
            '<strong>Ejemplos cotidianos de estas pautas:</strong><ol><li><strong>Continuar después de decidir</strong><br>Has elegido entre dos actividades que te interesaban. Más tarde recuerdas una ventaja de la opción descartada, pero no ha cambiado ninguna condición importante. Puedes reconocer que también tenía cosas buenas y mantener tu elección, sin volver a comparar todo desde el principio. Empiezas a conocer la experiencia que elegiste.</li><li><strong>Disfrutar de una capacidad sin buscar valoración</strong><br>Estás aprendiendo a preparar una receta y te interesa comprender por qué un paso cambia el resultado. Pruebas, observas y descubres algo que quieres recordar. Aunque nadie vaya a probarla ese día, encuentras satisfacción en lo que has aprendido. El interés está presente durante la actividad, no únicamente en la reacción que podría recibir.</li><li><strong>Compartir espacio con la iniciativa ajena</strong><br>En una conversación de grupo, otra persona propone un tema y conduce buena parte del intercambio. Tú intervienes cuando tienes algo que decir y disfrutas escuchando el resto. Al terminar, no necesitas medir cuánto has hablado para sentir que has formado parte del encuentro.</li><li><strong>Permitir una cercanía sencilla</strong><br>Compartes un paseo con alguien a quien aprecias. Habláis de cosas cotidianas y también hay ratos de silencio. No aparece ningún asunto profundo, pero encuentras a gusto. Puedes reconocer ese bienestar sin preguntarte si al encuentro le ha faltado algo para ser significativo.</li><li><strong>Dar por terminada una experiencia</strong><br>Después de dedicar una tarde a una actividad que te gusta, recoges lo utilizado y notas cansancio junto con satisfacción. Puedes pasar a descansar o hacer otra cosa sin seguir repasando cada detalle. Al día siguiente, recordar cómo te quedaste te ayuda a decidir si quieres repetirla con la misma duración o ajustar el tiempo.</li></ol>',
        ];
    }

    private function solDeficitLibraSix(): array
    {
        return [
            'La expresión por defecto puede comenzar con una respuesta que resulta cómoda en ese momento. Pastora, dejas pasar una diferencia, reduces una petición o esperas a que alguien tome la iniciativa, y así evitas tener que decidir o explicar algo que todavía no tienes claro. La dificultad aparece cuando se repite sin que vuelvas a elegirla.',
            'Ese movimiento puede mantenerse porque su efecto inmediato y su coste aparecen en momentos diferentes. Al callar una preferencia, la conversación continúa sin interrupciones; la incomodidad quizá llegue después, cuando estés viviendo una decisión que no te representa. La casa VI ayuda a seguir esa secuencia en situaciones repetidas.',
            'También influye la falta de práctica. Si rara vez propones, pides o negocias, tienes pocas oportunidades de descubrir cómo hacerlo y qué respuestas puedes recibir. Trabajar el defecto requiere permitir un aprendizaje gradual: formular una petición sencilla, comprobar cómo se entiende y ajustar lo necesario.',
            'Venus en Escorpio añade un matiz cuando el encuentro tiene mucho significado para ti: cuanto más importa una relación, más consecuencias puedes imaginar antes de mostrar una necesidad. Conviene distinguir la reserva elegida de la reserva automática.',
        ];
    }

    private function solExcessLibraSix(): array
    {
        return [
            'En la expresión por exceso las capacidades de esta combinación están disponibles, pero cuesta reconocer cuándo ya han cumplido su función. Pastora, has escuchado las distintas opiniones y sigues consultando; has explicado tu decisión y continúas justificándola; has ofrecido ayuda y encuentras otra cosa de la que ocuparte.',
            'Una dificultad particular aparece cuando intentas cerrar algo que depende también de otra persona. Puedes cuidar tus palabras, pero no decidir cómo serán recibidas; puedes proponer una solución, pero no conseguir por tu cuenta que todos la acepten. Reconocer qué parte puedes atender y qué parte corresponde al otro permite encontrar un punto de cierre.',
            'Venus en Escorpio puede añadir peso a esa insistencia cuando lo que está en juego tiene significado afectivo. Una decisión cotidiana empieza a representar algo mayor: que te valoran, que cuentan contigo o que la relación es importante. Conviene identificar qué necesitas resolver y qué necesitas sentir.',
            'El exceso también puede recibir aprobación y, por eso, tardar en hacerse visible. Si habitualmente facilitas, anticipas y resuelves, quizá el entorno agradezca esa disponibilidad y empiece a contar con ella. El aprendizaje consiste en poder utilizar el recurso y también detenerlo.',
        ];
    }

    private function lunaHarmonyLeoFour(): array
    {
        return [
            'En armonía, esta Luna puede expresar afecto con generosidad y recibirlo sin exigir una forma idéntica. Puedes mostrar una ilusión, reconocer una herida y pedir un gesto concreto, conservando perspectiva sobre lo ocurrido.',
            'El Sol en Libra ayuda a que la expresión tenga en cuenta al otro sin dejarte fuera. La casa IV ofrece una pregunta práctica: qué hace que tu espacio cercano sea un lugar donde descansar de la necesidad de estar demostrando, cumpliendo o manteniendo una imagen.',
            'Otra señal de equilibrio aparece en la recuperación. Una emoción puede ocupar bastante espacio durante un rato y, después de ser reconocida, empezar a transformarse. La armonía se reconoce también en esa capacidad de moverte con la emoción sin que tenga que desaparecer para poder continuar.',
            'En lo íntimo, el equilibrio permite convivir con ritmos afectivos diferentes. Puedes desear conversación y aceptar que otra persona necesite tiempo; puedes preferir celebrar algo con entusiasmo y reconocer que alguien lo exprese de forma más tranquila.',
        ];
    }

    private function lunaDeficitLeoFour(): array
    {
        return [
            'Aquí observamos recursos que necesitan más espacio para expresarse. El defecto puede instalarse de forma silenciosa porque muchas de sus respuestas permiten seguir funcionando: si reduces una necesidad, el día continúa; si no cuentas una alegría, nadie tiene que reaccionar; si dices que algo no importa, evitas exponerte.',
            'También puede faltar práctica. Reconocer una emoción y pedir algo concreto son habilidades diferentes. Puedes saber que estás triste y no saber todavía si necesitas compañía, descanso o espacio. La ausencia de una respuesta clara no significa que la emoción sea exagerada.',
            'En esta Luna, el defecto no equivale a ser reservada. Reservarte puede ser una elección sana cuando no existe confianza, cuando necesitas tiempo o cuando simplemente no deseas compartir. La dificultad aparece cuando sí quieres cercanía, celebración o un lugar propio y esa necesidad queda repetidamente fuera de la experiencia.',
            'El coste puede aparecer después: distancia, cansancio o sensación de no haber sido atendida. La práctica consiste en devolver lenguaje y espacio a una emoción de intensidad manejable antes de que tenga que crecer para ser reconocida.',
        ];
    }

    private function lunaExcessLeoFour(): array
    {
        return [
            'En el exceso, la necesidad emocional está muy disponible, pero cuesta reconocer cuándo ya ha sido atendida lo suficiente. Una muestra de cariño tranquiliza y enseguida aparece la pregunta de si seguirá estando; una diferencia concreta vuelve a abrir la duda sobre el vínculo completo.',
            'La casa IV puede amplificar este mecanismo porque lo íntimo tiene mucho significado. Un silencio, un cambio de tono o una preferencia distinta dentro del espacio cercano puede sentirse más cargado que el mismo hecho en otro contexto. La intensidad no prueba que la interpretación sea correcta.',
            'El Sol regente en Libra y casa VI añade otra vía de exceso: hacer, organizar o cuidar más para recuperar armonía. Esa actividad puede generar resultados visibles y, sin embargo, dejar intacta la necesidad original. Conviene identificar si intentas resolver una tarea o conseguir una señal afectiva.',
            'La emoción intensa no es por sí sola exceso. El exceso se reconoce cuando un recurso continúa operando más allá de lo que la escena necesita o impide integrar otros datos. La medida permite sentir y expresarte sin hacer depender toda la seguridad del comportamiento ajeno.',
        ];
    }

    private function solHouseSix(array $context): array
    {
        $name = $context['name'];

        return [
            'La casa VI y lo que haces cada día',
            sprintf('%s, si el signo nos ayuda a comprender cómo se expresa tu Sol, la casa señala en qué terreno de la vida podemos observarlo. La casa VI nos lleva a las actividades que sostienen tus días: preparar lo necesario, atender una responsabilidad, aprender una tarea o encontrar una manera de organizarte que funcione. Muchas de estas acciones apenas llaman la atención, pero su repetición va dando forma a tu vida. Con el Sol en esta casa, podemos explorar qué descubres de ti al participar en ese trabajo cotidiano y qué satisfacción encuentras al ver que tu aportación produce un efecto concreto.', $name),
            'Uno de los temas de la casa VI es el aprendizaje mediante la práctica. Hay capacidades que solo se desarrollan al hacer, equivocarse, ajustar y volver a intentarlo. Al principio necesitas prestar atención a cada paso; con el tiempo, adquieres soltura y empiezas a reconocer tu propio modo de trabajar. En tu lectura, este terreno permite preguntarte qué habilidades deseas cultivar y cómo valoras tus avances. Quizá algo que hoy te parece sencillo requirió tiempo y esfuerzo para llegar a serlo. Recordar ese proceso puede ayudarte a reconocer capacidades que has dejado de apreciar porque ya forman parte de tu día a día.',
            'La casa VI también invita a examinar tus hábitos. Una costumbre puede ahorrarte esfuerzo, darte continuidad y facilitar que atiendas lo importante. Sin embargo, puede seguir repitiéndose mucho después de haber dejado de ser útil. Revisar tus rutinas permite comprobar qué te facilitan, qué te complican y cuáles mantienes sin haber vuelto a elegirlas.',
            'Otro aspecto de esta casa es la relación entre el trabajo y las condiciones en las que lo realizas. A veces una tarea resulta difícil porque faltan instrucciones, tiempo, materiales o un reparto claro. Observar este terreno implica preguntar qué depende de ti y qué necesita una modificación del entorno antes de exigirte hacer más.',
            'En la colaboración cotidiana existe un trabajo que suele pasar desapercibido: recordar lo pendiente, anticipar necesidades, coordinar horarios o comprobar que las distintas partes encajan. Esta parte de tu carta ofrece una ocasión para reconocer ese trabajo y hacerlo visible, de modo que las responsabilidades puedan distribuirse con conocimiento de lo que realmente implican.',
            'La ayuda y el servicio también pertenecen al lenguaje de la casa VI. Contribuir puede ofrecer satisfacción, aprendizaje y una sensación de participación. Interesa observar qué tipo de ayuda te gusta ofrecer y qué sucede después: si la otra persona gana autonomía, si el reparto funciona o si acabas ocupando un lugar del que resulta difícil salir.',
            'Esta casa incluye igualmente el cuidado cotidiano del cuerpo: cómo distribuyes la actividad, qué lugar tiene el descanso y cuánto margen dejas entre una obligación y la siguiente. La posición astrológica no permite deducir un estado de salud; sirve para abrir preguntas sobre las condiciones en las que transcurre tu día.',
            'Con tu Sol en casa VI, el desarrollo personal puede encontrar oportunidades en mejoras pequeñas y concretas: aprender algo que te interese, resolver con más claridad una dificultad o modificar una rutina que ya no encaja. Conviene que también puedas reconocer cuándo una tarea está terminada. Si toda mejora abre inmediatamente una nueva exigencia, apenas queda ocasión de disfrutar lo conseguido. Este terreno invita a construir confianza en tus capacidades mediante la experiencia, dejando que lo que haces sea una expresión de ti y que tu vida conserve espacio para otras fuentes de satisfacción.',
            '<strong>Para observar esta casa en tu vida cotidiana:</strong><ul><li>Dar continuidad a una habilidad que te interese y reconocer cómo evoluciona.</li><li>Revisar si tus hábitos siguen respondiendo a tus circunstancias actuales.</li><li>Comprobar las condiciones de una tarea antes de atribuirte toda la dificultad.</li><li>Hacer visible el tiempo dedicado a preparar, recordar y coordinar.</li><li>Elegir formas de ayuda que favorezcan un reparto claro de responsabilidades.</li><li>Incluir pausas e imprevistos al organizar el día.</li><li>Reconocer cuándo algo está suficientemente bien y permitirte darlo por terminado.</li></ul>',
        ];
    }

    private function lunaHouseFour(array $context): array
    {
        return [
            'La casa IV y tu forma de habitar lo íntimo',
            'La casa IV se asocia con hogar, raíces, pertenencia y vida privada. Puede explorarse tanto en el espacio donde vives como en la manera de construir un refugio propio. No permite afirmar cómo fue tu infancia, quién te cuidó ni qué sucede en tu familia: esas son experiencias que solo tú puedes contar.',
            'Con la Luna aquí, conviene observar qué condiciones te ayudan a bajar la guardia. Tal vez sean un trato cálido, una rutina de encuentro, un lugar que sientas tuyo o el permiso para mostrarte sin estar resolviendo nada. Lo íntimo puede necesitar algo más que funcionar bien: también necesita sentirse habitable.',
            'La casa IV ayuda a observar la diferencia entre estar en un lugar y sentir que puedes habitarlo. En un espacio compartido no todo tiene que organizarse según ti, pero tu bienestar necesita ser uno de los datos presentes. Esta pregunta puede aplicarse a una vivienda, una habitación, una convivencia temporal o cualquier entorno donde esperas poder bajar parte de la vigilancia cotidiana.',
            'También importa la frontera entre lo público y lo privado. Hay experiencias que puedes contar con facilidad fuera y otras que solo deseas compartir cuando existe confianza. Reconocer ese umbral permite elegir mejor dónde procesas una emoción; no toda conversación necesita ocurrir en el momento ni delante de todas las personas presentes.',
            'La pertenencia puede hacerse visible en pequeños rituales: una forma de llegar a casa, un momento de encuentro, una costumbre que señala descanso o una manera de celebrar. Su valor no está en repetirlos siempre igual, sino en que ayuden a reconocer «aquí puedo volver a mí». Cuando una rutina deja de producir ese efecto, puede revisarse.',
            'La casa IV también abre la puerta a los recuerdos, sin permitirnos deducir qué ocurrió en tu historia. Una situación actual puede recordarte otra etapa y hacer que la emoción sea más intensa de lo que el hecho presente explica por sí solo. Puede ayudarte distinguir qué está ocurriendo ahora, qué te recuerda y qué necesitas en esta escena concreta.',
            '<ul><li>Reconocer qué te hace sentir pertenencia.</li><li>Reservar un lugar para tus preferencias en lo cercano.</li><li>Dar espacio al afecto y a la celebración.</li><li>Distinguir recuerdos, expectativas y hechos actuales.</li></ul>',
        ];
    }

    private function solLibraSixSign(array $context): array
    {
        return [
            '<ul><li>Reciprocidad en el dar y recibir.</li><li>Diálogo y capacidad de considerar matices.</li><li>Buen trato y sensibilidad hacia las formas.</li><li>Acuerdos donde las posiciones estén representadas.</li><li>Espacio para el criterio y las preferencias propias.</li></ul>',
            sprintf('%s, Libra nos acerca a algo que sucede cuando entramos en relación con otras personas: descubrimos que nuestra manera de ver las cosas no es la única. Una conversación puede mostrarte un detalle que no habías considerado, ayudarte a precisar una opinión o hacerte reconocer una preferencia que hasta entonces no tenías clara. En el lenguaje astrológico, tu Sol en Libra invita a desarrollar esa capacidad de mirar desde distintos lugares y formar, a partir de ahí, un juicio propio. Escuchar puede enriquecer tu elección; el aprendizaje consiste en reconocer cuándo ya tienes suficiente perspectiva para decidir.', $context['name']),
            'Libra pertenece al elemento aire, asociado con el pensamiento, las palabras y el intercambio de ideas. Esta cualidad propone tomar cierta distancia de una situación para comprenderla mejor. Imagina que dos personas cuentan versiones diferentes de un mismo hecho: quizá una habla de lo que ocurrió y otra de cómo se sintió tratada. Ambas pueden estar señalando algo relevante, aunque parezcan contradecirse. La mirada libriana busca distinguir esas capas. Su interés está en comprender qué está defendiendo cada persona y qué información falta antes de sacar una conclusión. Esa perspectiva puede ser especialmente útil cuando una primera impresión parece explicarlo todo demasiado rápido.',
            'Además, Libra es un signo cardinal. Esta palabra se refiere a la capacidad de iniciar, y aquí la iniciativa puede tomar una forma discreta pero decisiva: hacer una pregunta que nadie se había atrevido a plantear, proponer otra manera de organizarse o señalar que un acuerdo necesita revisión. A veces se asocia Libra únicamente con adaptarse, pero su simbolismo también contiene la capacidad de intervenir. Puedes abrir una conversación y cambiar el curso de una situación sin levantar la voz. Lo que pone algo en movimiento es reconocer que existe una diferencia y ofrecer una forma de abordarla.',
            'La justicia es otro de sus temas, y merece una mirada más profunda que repartir todo por igual. Dos personas pueden recibir lo mismo y encontrarse en condiciones muy distintas. Un acuerdo que fue razonable hace un tiempo puede dejar de serlo si cambian las responsabilidades o las posibilidades de alguien. Por eso, la reciprocidad necesita atención y revisión. Para explorar esta cualidad en ti, puede ser útil preguntarte qué hace que un intercambio resulte justo: si ambas personas pueden participar en las decisiones, si conocen lo que se espera de ellas y si tienen libertad para expresar que algo ya no les funciona.',
            'Libra también se relaciona con la belleza, la proporción y el cuidado de las formas. Esto puede explorarse más allá de la apariencia: en cómo se recibe a alguien, en el tono de una conversación o en la atención con la que se prepara un espacio compartido. La forma influye en cómo vivimos el contenido. Una observación puede ayudar o cerrar el diálogo según cómo se exprese. Desarrollar esta sensibilidad permite cuidar la manera de comunicar algo importante, procurando que la delicadeza conserve también la claridad del mensaje.',
            'En tu recorrido solar, estas cualidades necesitan convivir con la posibilidad de elegir sin conseguir unanimidad. Habrá decisiones en las que comprendas sinceramente la posición de otra persona y aun así quieras algo diferente. También habrá situaciones que no admitan un punto medio satisfactorio. Ahí puedes desarrollar una parte valiosa de Libra: expresar una diferencia con respeto y aceptar que el desacuerdo permanezca. Tu criterio se fortalece cuando puedes explicar qué has considerado, qué valor tiene para ti y por qué eliges ese camino, aunque alguien a quien aprecias hubiera elegido otro.',
        ];
    }

    private function solVenusRuler(array $context): array
    {
        return [
            'Venus en Escorpio en casa VI',
            sprintf('%s, para comprender tu Sol en Libra necesitamos conocer también a Venus, su planeta regente. Su posición permite añadir una pregunta más personal: ¿qué valoras en ese encuentro, qué hace que quieras implicarte y qué necesitas para sentir que merece la pena? En tu carta, Venus se encuentra a %d° %d′ %d″ de Escorpio, en casa VI.', $context['name'], $context['ruler_degrees'] ?? 15, $context['ruler_minutes'] ?? 30, $context['ruler_seconds'] ?? 6),
            'Venus representa simbólicamente aquello que apreciamos, lo que nos atrae y nuestras formas de dar y recibir afecto. Escorpio introduce el deseo de conocer algo más allá de la primera impresión y de comprobar si las palabras y los hechos tienen contenido real.',
            'Esa profundidad necesita tiempo para construirse. Puedes desear cercanía y conservar asuntos que todavía no quieres contar; ambas necesidades pueden convivir. Abrirte de forma gradual permite comprobar cómo se recibe lo que confías y si puedes marcar un límite sin recibir presión.',
            'Al situarse Venus en casa VI, estas preguntas encuentran expresión en gestos habituales: prestar atención, recordar una necesidad, cumplir una responsabilidad o facilitar algo en un momento difícil. Conviene observar qué acciones tienen significado para ti sin utilizarlas como medida de toda una relación.',
            'En el cálculo utilizado, Venus puede estar cerca del Descendente y permanecer en casa VI según las cúspides adoptadas. Por eso se mantiene aquí el énfasis en las acciones cotidianas y se conserva diferenciada la posición por signo y por casa.',
            '<strong>Pautas y consideraciones para llevarlo a tu experiencia:</strong><ul><li><strong>Identifica qué hace valioso un vínculo para ti.</strong> Piensa en una relación que aprecies y concreta qué encuentras en ella: sinceridad, interés, discreción, compañía o libertad para mostrarte. Reconocerlo te ayuda a comprender qué deseas cultivar en tus relaciones.</li><li><strong>Deja que la confianza avance al ritmo de lo que vais viviendo.</strong> Puedes compartir primero algo de menor importancia y observar cómo se recibe antes de abrir asuntos más personales. La cercanía no necesita construirse de golpe.</li><li><strong>Observa qué ocurre después de un error.</strong> Además de escuchar una disculpa, comprueba si la persona reconoce lo sucedido, se interesa por su efecto e intenta modificar lo necesario. La reparación se conoce también por lo que ocurre después de hablar.</li><li><strong>Expresa qué gestos te hacen sentir querida.</strong> En lugar de esperar que alguien lo descubra, puedes decir: «Para mí significa mucho que recuerdes esto» o «Me ayuda que me preguntes cómo me ha ido». Dar esa información facilita que el cuidado responda a lo que necesitas.</li><li><strong>Separa el hecho de su significado para ti.</strong> Ante una decepción, anota qué ocurrió y qué conclusión sacaste. «Olvidó llamarme» y «no le importo» no contienen la misma información. Preguntar por lo sucedido puede ayudarte a comprenderlo antes de valorar el vínculo completo.</li><li><strong>Distingue una ocasión aislada de una conducta repetida.</strong> Ten en cuenta el contexto y observa si, después de hablarlo, se producen cambios. Esto permite reconocer tanto una dificultad puntual como un problema que sigue sin atenderse.</li><li><strong>Conserva la libertad de decidir qué compartes.</strong> Puedes decir «todavía no quiero hablar de esto» y respetar que la otra persona haga lo mismo. Observa si ambos podéis expresar ese límite sin presión ni exigencias de demostrar confianza.</li><li><strong>Reconoce formas de afecto diferentes de la tuya.</strong> Pregunta cómo suele demostrar cariño la otra persona y observa qué recibes por esa vía. Después explica qué necesitas tú, para que las diferencias puedan conocerse y atenderse.</li><li><strong>Haz visibles las expectativas cotidianas.</strong> Cuando algo que parecía acordado genere malestar, pregunta: «¿Qué entendió cada persona que iba a ocurrir?». Aclararlo permite decidir qué mantener, qué cambiar y qué conviene concretar mejor la próxima vez.</li></ul>',
        ];
    }

    private function lunaSolRuler(array $context): array
    {
        return [
            'El Sol en Libra en casa VI',
            sprintf('Leo está regido por el Sol. Para comprender esta Luna, volvemos al Sol a %d° %d′ %d″ de Libra, en casa VI. La necesidad lunar de calidez puede buscar una vía a través de colaborar, cuidar el trato y hacer cosas útiles.', $context['ruler_degrees'] ?? 5, $context['ruler_minutes'] ?? 23, $context['ruler_seconds'] ?? 33),
            'Esos gestos pueden ser valiosos; conviene observar si también puedes pedir atención de manera directa cuando eso es lo que necesitas. El Sol regente muestra cómo una necesidad emocional intenta convertirse en algo expresable.',
            'Libra puede ayudarte a traducir «me siento poco acompañada» en una petición que la otra persona pueda comprender. La casa VI introduce una comprobación útil: qué forma cotidiana tiene el cuidado que necesitas y si una dinámica repetida necesita ajuste.',
            'También conviene distinguir dar cuidado de recibirlo. Participar, organizar o colaborar no sustituye automáticamente aquello que necesitas emocionalmente. Preguntarte si quieres hacer algo o si intentas llegar al afecto por esa vía permite elegir con más precisión.',
            '<strong>Pautas y consideraciones para llevarlo a tu experiencia:</strong><ul><li>Poner palabras a la emoción.</li><li>Pedir un gesto concreto.</li><li>Observar si el cuidado es puntual o repetido.</li><li>Distinguir ayudar de recibir.</li><li>Revisar una rutina que esté informando de agotamiento.</li></ul>',
        ];
    }

    private function ascendantVenusRuler(array $context): array
    {
        return [
            sprintf('Venus en %s en casa %s', $context['ruler_sign'], $this->romanHouse((int) $context['ruler_house'])),
            sprintf('Tauro está regido por Venus, planeta asociado con valor, placer y afinidad. En tu carta, Venus se encuentra a %d° %d′ %d″ de %s, en casa %s. Esta posición matiza tu forma de entrar en la vida: puedes buscar base no solo en lo conocido, sino también en sentir que existe compromiso y coherencia.', $context['ruler_degrees'] ?? 0, $context['ruler_minutes'] ?? 0, $context['ruler_seconds'] ?? 0, $context['ruler_sign'], $this->romanHouse((int) $context['ruler_house'])),
            'Como regente del Ascendente, Venus aporta una pregunta de valor al comienzo: no solo «¿puedo hacerlo?», sino «¿merece la pena para mí?». Dos propuestas pueden ser igualmente posibles y dejarte sensaciones diferentes porque una conecta mejor con lo que aprecias o consideras significativo.',
            'La confianza puede crecer mediante pequeñas comprobaciones ordinarias: se cumple un horario, se respeta un no, se corrige un error o se habla de un cambio. Esta lectura es distinta de vigilar; consiste en dejar que la experiencia aporte información.',
            'La casa del regente sitúa esa información en un terreno concreto. Una primera impresión puede abrir una puerta, pero la continuidad muestra si el funcionamiento cotidiano es viable. Date un plazo suficiente para distinguir novedad de incompatibilidad y hábito útil de costumbre automática.',
            '<strong>Pautas y consideraciones para llevarlo a tu experiencia:</strong><ul><li>Comprobar qué merece la pena antes de comprometerte.</li><li>Observar hechos repetidos.</li><li>Conservar el ritmo propio.</li><li>Distinguir compromiso de control.</li><li>Revisar si la forma de comenzar sigue cuidando tus valores actuales.</li></ul>',
        ];
    }

    private function descendantRulers(array $context): array
    {
        return [
            'Escorpio tiene dos regencias que aquí se leen de forma diferenciada: Marte como vía tradicional de acción y Plutón como vía moderna de revisión profunda.',
            'Marte en Capricornio en casa IX aporta deseo, acción y defensa de límites. Capricornio añade estructura, perseverancia y atención a las consecuencias; la casa IX dirige la lectura hacia convicciones, aprendizaje, visión de vida y criterios con los que orientas tus decisiones.',
            'En un vínculo, Marte puede ayudar a formular una pregunta, proponer un acuerdo, marcar un límite o decidir una dirección. Actuar no significa reaccionar deprisa: puede significar elegir el momento, considerar consecuencias y sostener lo que se ha decidido.',
            'La casa IX introduce diferencias de horizonte y de valores que no siempre se resuelven repartiendo una tarea. La igualdad requiere escuchar qué considera esencial la otra persona sin presentar tu criterio como la única manera válida de vivir.',
            'Plutón en Escorpio en casa VI añade profundidad, revisión de patrones y relación con el poder en las formas cotidianas de colaborar. Puede ser útil observar quién decide, quién revisa, quién se adapta o quién termina haciendo lo pendiente.',
            'Plutón permanece años en un signo, de modo que parte de esta posición es generacional. En esta lectura tiene más peso la función que adquiere al regir el Descendente y su ubicación en casa VI que una descripción individual basada solo en Plutón en Escorpio.',
            'Marte y Plutón pueden colaborar cuando primero reconoces el patrón y después eliges una acción concreta. La transformación deja de ser una idea abstracta y se convierte en un cambio observable en el modo de relacionaros.',
            '<strong>Pautas y consideraciones para llevarlo a tu experiencia:</strong><ul><li>Aclara qué parte es un hecho y cuál una interpretación.</li><li>Expresa el acuerdo que necesitas.</li><li>Revisa quién decide y quién asume lo pendiente.</li><li>Conserva la posibilidad de renegociar.</li><li>Respeta la privacidad que no te corresponde dirigir.</li></ul>',
        ];
    }

    private function houseProfile(int $house): array
    {
        $profiles = [
            1 => ['meaning' => 'La casa I señala la posición de partida, la presencia propia y el modo de entrar en las experiencias.', 'experiences' => 'Puede observarse al comenzar, decidir, orientarte y mostrar qué necesitas conservar para participar.', 'needs' => 'Pide una referencia propia desde la que poder adaptarte sin desaparecer dentro de las demandas externas.', 'example' => 'Un cambio de planes puede ayudarte a comprobar si estás respondiendo a tu ritmo o a la prisa de otras personas.'],
            2 => ['meaning' => 'La casa II lleva la lectura a recursos, valores, economía cotidiana y aquello que ayuda a sostener la vida.', 'experiences' => 'Puede aparecer al administrar tiempo, dinero, energía, habilidades y prioridades personales.', 'needs' => 'Pide reconocer lo que tienes y diferenciar valor propio de cantidad o rendimiento.', 'example' => 'Antes de aceptar algo puedes comprobar qué recursos requiere y qué condiciones necesitas conservar.'],
            3 => ['meaning' => 'La casa III se relaciona con aprendizaje cercano, comunicación, desplazamientos y entorno cotidiano.', 'experiences' => 'Puede observarse en conversaciones, decisiones prácticas, preguntas y formas de organizar información.', 'needs' => 'Pide intercambio claro y libertad para revisar una primera interpretación.', 'example' => 'Una conversación confusa puede mejorar si separas el hecho, la pregunta y la petición concreta.'],
            4 => ['meaning' => 'La casa IV se asocia con hogar, pertenencia, intimidad y la forma de construir un refugio propio.', 'experiences' => 'Puede observarse en la vida privada, los ritmos domésticos, el descanso y los espacios donde bajas la vigilancia.', 'needs' => 'Pide que tus preferencias tengan lugar sin asumir que toda la convivencia debe organizarse según ellas.', 'example' => 'Un espacio compartido puede funcionar y necesitar también una condición concreta de calma o afecto.'],
            5 => ['meaning' => 'La casa V lleva la lectura a creatividad, disfrute, juego, expresión personal y proyectos que nacen de ti.', 'experiences' => 'Puede hacerse visible al crear, celebrar, expresar entusiasmo o permitirte una actividad sin rendimiento.', 'needs' => 'Pide placer y expresión sin convertirlos en una obligación de destacar.', 'example' => 'Puedes reservar un tiempo para algo que te guste aunque no produzca un resultado útil.'],
            6 => ['meaning' => 'La casa VI señala tareas, hábitos, aprendizaje por práctica, colaboración y cuidado cotidiano del tiempo.', 'experiences' => 'Puede observarse en responsabilidades repetidas, organización, reparto de tareas y condiciones de trabajo.', 'needs' => 'Pide distinguir ayuda de sobrecarga y revisar si tus rutinas siguen sirviendo a tu vida actual.', 'example' => 'Antes de hacer más puedes comprobar instrucciones, tiempo, materiales y reparto real de responsabilidades.'],
            7 => ['meaning' => 'La casa VII observa vínculos significativos, acuerdos, asociaciones y decisiones entre dos centros de voluntad.', 'experiences' => 'Puede aparecer en confianza, expectativas, límites, compromisos y negociación de diferencias.', 'needs' => 'Pide expresar deseo, petición y norma sin confundirlos, conservando autonomía dentro del vínculo.', 'example' => 'Una preferencia abre una conversación; solo un acuerdo aceptado por ambas personas crea responsabilidad compartida.'],
            8 => ['meaning' => 'La casa VIII explora intimidad, confianza, recursos compartidos y transformaciones que afectan a más de una persona.', 'experiences' => 'Puede observarse al compartir responsabilidades, vulnerabilidad, dinero, secretos o decisiones profundas.', 'needs' => 'Pide claridad, consentimiento y libertad para revisar lo compartido.', 'example' => 'Antes de asumir un compromiso común puedes concretar qué aporta cada persona y cuándo se revisa.'],
            9 => ['meaning' => 'La casa IX se relaciona con estudios, convicciones, sentido, horizontes y criterios que orientan el camino.', 'experiences' => 'Puede aparecer al aprender, viajar, enseñar, defender valores o elegir una dirección vital.', 'needs' => 'Pide una visión amplia que permita sostener principios sin convertirlos en ley para los demás.', 'example' => 'Un proyecto puede tener una dirección clara y admitir revisión cuando la experiencia aporta información nueva.'],
            10 => ['meaning' => 'La casa X lleva la lectura a vocación, responsabilidad pública, autoridad y objetivos visibles.', 'experiences' => 'Puede observarse en decisiones profesionales, reconocimiento, liderazgo y relación con expectativas externas.', 'needs' => 'Pide construir una dirección propia sin reducir el valor personal a los resultados visibles.', 'example' => 'Puedes revisar si una meta sigue siendo tuya o si la mantienes solo porque otras personas la reconocen.'],
            11 => ['meaning' => 'La casa XI se vincula con amistades, grupos, proyectos colectivos y futuros posibles.', 'experiences' => 'Puede aparecer al participar en redes, imaginar cambios y colaborar con personas que comparten intereses.', 'needs' => 'Pide pertenencia con límites claros y reciprocidad comprobable.', 'example' => 'Antes de ofrecer disponibilidad a un grupo puedes preguntar qué tareas y tiempo implica realmente.'],
            12 => ['meaning' => 'La casa XII abre preguntas sobre retiro, mundo interior, descanso y procesos que necesitan silencio.', 'experiences' => 'Puede observarse en la necesidad de desconectar, procesar emociones y soltar cargas que no te corresponden.', 'needs' => 'Pide intimidad y límites para que la sensibilidad no se convierta en disponibilidad permanente.', 'example' => 'Un periodo de descanso puede ser una forma consciente de recuperar recursos, no una señal de falta de participación.'],
        ];

        return $profiles[$house] ?? $profiles[1];
    }

    private function stateFeatures(string $door, string $sign): array
    {
        $prefix = match ($door) {
            'sol' => ['deficit' => ['Dejar tu opinión fuera de la decisión', 'Evitar una conversación que podría aclarar', 'Pedir menos reciprocidad de la que necesitas', 'Infravalorar tu contribución', 'Confundir discreción con no ocupar espacio', 'Retirarte antes de comprobar si se puede colaborar', 'Vivir en respuesta de las obligaciones'], 'excess' => ['Adaptarte hasta perder tu posición', 'Necesitar agradar para sentirte coherente', 'Sostener todas las tensiones del entorno', 'Convertir el compromiso en una prueba de lealtad', 'Buscar el acuerdo perfecto', 'Ofrecer más para asegurar tu lugar', 'Guardar la cuenta de las concesiones']],
            'luna' => ['deficit' => ['Ocultar que necesitas afecto explícito', 'Restar importancia a tu alegría', 'Cuidar el ambiente antes de mostrar tu emoción', 'Pedir poco espacio en tu refugio', 'Desconectarte del juego y la espontaneidad', 'Minimizar una herida', 'Dejar que otros definan cómo sentirte'], 'excess' => ['Depender del reconocimiento cercano', 'Interpretar una diferencia como falta de cariño', 'Sostener el orgullo al necesitar cercanía', 'Organizar el refugio según una única forma', 'Dar afecto esperando devolución idéntica', 'Convertir una emoción momentánea en toda la escena', 'Buscar confirmación mediante sobrecuidado']],
            'ascendente' => ['deficit' => ['No darte tiempo para orientarte', 'Dejar poco espacio a tus preferencias', 'Descuidar tu base por sostener un vínculo', 'Desconocer los recursos que ya tienes', 'Abandonar antes de familiarizarte', 'Posponer el disfrute hasta resolverlo todo', 'No expresar tu velocidad'], 'excess' => ['Conservar porque resulta familiar', 'Esperar seguridad completa', 'Hacer del hábito una regla inamovible', 'Controlar cada detalle', 'Mantener una decisión para no ceder posición', 'Confundir cercanía con disponibilidad estable', 'Reducir la experiencia para evitar incomodidad']],
            default => ['deficit' => ['Reservarte lo que más importa', 'Poner estructura sin expresar deseo', 'Aplazar un límite', 'No revisar acuerdos cotidianos', 'Delegar tus convicciones', 'Cerrar la confianza por anticipado', 'Evitar preguntas importantes'], 'excess' => ['Comprobar la lealtad continuamente', 'Convertir tu criterio en ley', 'Organizar el proceso del otro', 'Cargar el último detalle con todo lo anterior', 'Confundir compromiso con resistencia ilimitada', 'Buscar explicación de cada emoción ajena', 'Hacer del vínculo el centro de toda decisión']],
        };

        return $prefix;
    }

    private function romanHouse(int $house): string
    {
        return [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'][$house] ?? (string) $house;
    }
}
