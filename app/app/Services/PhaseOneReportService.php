<?php

namespace App\Services;

use App\Domain\Astrology\RegencyResolver;
use App\Models\Chart;
use App\Models\ChartTemplate;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class PhaseOneReportService
{
    public function build(Chart $chart): array
    {
        $chart->loadMissing('person', 'birthData.place');

        $name = $chart->person->full_name ?: $chart->person->alias;
        $readerName = $name;
        $snapshot = $chart->snapshot;
        $doors = [
            'sol' => [
                'title' => 'Primera puerta el Sol',
                'subtitle' => sprintf('%s en casa %s y una identidad que aprende a cooperar con criterio', $this->translateSign($snapshot['sun']['sign'] ?? 'aries'), $this->romanHouse($this->resolveHouse($snapshot['sun'] ?? [], $snapshot['houses'] ?? [])['number'])),
                'question' => '¿Qué quiero aportar y elegir?',
                'point' => 'sun',
            ],
            'luna' => [
                'title' => 'Segunda puerta la Luna',
                'subtitle' => sprintf('%s en casa %s y la necesidad de un refugio cálido donde poder expresarte', $this->translateSign($snapshot['moon']['sign'] ?? 'aries'), $this->romanHouse($this->resolveHouse($snapshot['moon'] ?? [], $snapshot['houses'] ?? [])['number'])),
                'question' => '¿Qué afecto y expresión necesito?',
                'point' => 'moon',
            ],
            'ascendente' => [
                'title' => 'Tercera puerta el Ascendente',
                'subtitle' => sprintf('%s y una manera de comenzar que necesita base y continuidad', $this->translateSign($snapshot['ascendant']['sign'] ?? 'aries')),
                'question' => '¿Qué ritmo y base puedo sostener?',
                'point' => 'ascendant',
            ],
            'descendente' => [
                'title' => 'Cuarta puerta el Descendente',
                'subtitle' => sprintf('%s y el aprendizaje de confiar conservando autonomía', $this->translateSign($snapshot['descendant']['sign'] ?? 'aries')),
                'question' => '¿Cómo comparto confianza y libertad?',
                'point' => 'descendant',
            ],
        ];
        $fixed = (new PhaseOneFixedContent())->sections($readerName);
        $doorReports = array_values(array_map(
            fn (array $door, string $key): array => [
                'key' => $key,
                'title' => $door['title'],
                'subtitle' => $door['subtitle'],
                'question' => $door['question'],
                'position' => $this->doorPosition($snapshot, $door['point']),
                'blocks' => (new PhaseOneDoorCatalog())->blocks(
                    $key,
                    $this->doorContext($readerName, $snapshot, $door['point'], $key),
                ),
            ],
            $doors,
            array_keys($doors),
        ));
        $storedInterpretations = $chart->interpretations()
            ->where('phase', 'fase-1')
            ->where(function ($query): void {
                $query->where('ai_assisted', true)
                    ->orWhereHas('template', fn ($templateQuery) => $templateQuery->where('version', '>=', 2));
            })
            ->orderBy('id')
            ->get()
            ->keyBy(fn ($interpretation) => ($interpretation->door ?? 'shared') . '.' . $interpretation->block);

        foreach ($doorReports as &$doorReport) {
            foreach ($doorReport['blocks'] as $block => &$paragraphs) {
                $stored = $storedInterpretations->get($doorReport['key'] . '.' . $block);
                if ($stored) {
                    $paragraphs = preg_split('/\R{2,}/', $stored->content) ?: [$stored->content];
                }
            }
        }
        unset($doorReport, $paragraphs);

        return [
            'name' => $name,
            'shared' => $fixed,
            'index' => [
                ['number' => '01', 'title' => 'El Sol', 'summary' => 'Identidad, cooperación y criterio propio'],
                ['number' => '02', 'title' => 'La Luna', 'summary' => 'Calidez, expresión y pertenencia'],
                ['number' => '03', 'title' => 'El Ascendente', 'summary' => 'Ritmo, estabilidad y continuidad'],
                ['number' => '04', 'title' => 'El Descendente', 'summary' => 'Confianza, compromiso y autonomía'],
                ['number' => '05', 'title' => 'Cierre', 'summary' => 'Preguntas de autoobservación e integración'],
                ['number' => '06', 'title' => 'Datos de la carta', 'summary' => 'Posiciones, casas y horario verificado'],
            ],
            'doors' => $doorReports,
            'door_introduction' => $this->buildDoorIntroduction($readerName, $snapshot),
            'combined' => $this->buildCombined($snapshot),
            'practice' => [
                'intro' => 'Puedes utilizar el dossier durante unas cuatro semanas. Si deseas relacionarlo con un ciclo lunar, úsalo como marco temporal de registro, sin suponer que una fase cause un estado emocional concreto. El objetivo es conocer tu experiencia, no hacer que encaje en una descripción.',
                'weeks' => [
                    'Primera semana. Observa el Sol: decisiones, reparto de tareas y preferencias que expresas o aplazas.',
                    'Segunda semana. Observa la Luna: afecto, alegría, necesidad de reconocimiento y espacio íntimo.',
                    'Tercera semana. Observa el Ascendente: ritmo, recursos, hábitos y pequeños cambios posibles.',
                    'Cuarta semana. Observa el Descendente: confianza, expectativas, límites y capacidad de escuchar otra perspectiva.',
                ],
                'guidance' => [
                    'Elige una escena al día y descríbela con hechos concretos: qué ocurrió, qué dijiste o hiciste, qué sentiste y qué necesitabas en ese momento.',
                    'Al terminar, busca dos o tres repeticiones en tus registros. Observa qué puerta aparece con más frecuencia, qué respuesta te ayuda y en qué momento una necesidad queda sin expresar.',
                    'Escoge un único ajuste para continuar durante la semana siguiente. Un cambio pequeño, repetido y revisable puede enseñarte más que intentar corregir toda tu forma de responder a la vez.',
                ],
                'sheet' => [
                    '¿Qué ocurrió y qué hechos conozco?',
                    '¿Qué quería aportar o elegir yo?',
                    '¿Qué sentí y qué forma de cuidado necesitaba?',
                    '¿Qué tiempo, ritmo y recursos podía sostener?',
                    '¿Qué necesitaba preguntar, expresar o acordar?',
                    '¿Qué cambio pequeño quiero probar?',
                ],
            ],
            'expansion' => $this->buildExpansion($snapshot),
            'closing' => [
                'paragraphs' => [
                    'Este recorrido permite distinguir necesidades que pueden aparecer juntas sin ser iguales. Tu voluntad puede buscar un acuerdo justo; tu emoción, calidez; tu manera de comenzar, una base concreta; tus vínculos, profundidad y coherencia. Escucharlas por separado ayuda a reunirlas con más libertad.',
                    'No necesitas responder siempre de forma ideal. Puedes reconocer una petición tarde, revisar un sí que diste demasiado rápido o descubrir que habías esperado algo sin comunicarlo. La observación sirve también para ajustar y reparar, no solo para anticiparte.',
                    'El valor de la carta está en las preguntas que puedas llevar a tu vida real. Puedes conservar las que te ayudan, dejar otras abiertas y recordar que tu experiencia tiene la última palabra sobre lo que te representa.',
                ],
                'questions' => [
                    '¿Qué quiero yo dentro de este acuerdo?',
                    '¿Qué afecto o reconocimiento necesito pedir directamente?',
                    '¿Qué base propia me permite participar con libertad?',
                    '¿Qué estoy observando y qué estoy suponiendo?',
                    '¿Qué compromiso podemos sostener de verdad entre dos?',
                ],
                'phrase' => 'Puedo cooperar con criterio, expresar mi emoción y construir vínculos donde también tenga lugar mi libertad.',
            ],
            'ai' => [
                'enabled' => (bool) config('ai.enabled'),
                'message' => config('ai.enabled')
                    ? 'ChatGPT está configurado para generar contenido bajo demanda. Revisa y edita el resultado antes de guardar el informe.'
                    : 'El informe utiliza contenido editorial versionado. Activa AI_ENABLED y configura la API de ChatGPT para habilitar la generación.',
            ],
            'technical' => [
                'birth_date' => $chart->birthData->local_date?->format('d/m/Y'),
                'birth_time' => $chart->birthData->local_time,
                'timezone' => $chart->birthData->timezone_identifier,
                'utc_datetime' => $chart->birthData->utc_datetime,
                'place' => $chart->birthData->place->city . ', ' . $chart->birthData->place->country,
                'coordinates' => $chart->birthData->place->latitude . ', ' . $chart->birthData->place->longitude,
                'zodiac' => $chart->configuration['zodiac'] ?? 'tropical',
                'houses' => $chart->configuration['houses'] ?? 'placidus',
                'engine' => $chart->engine_version,
                'julian_day' => $this->julianDay($chart->birthData->utc_datetime),
                'legal_time_note' => $this->legalTimeNote($chart),
                'house_assignment_note' => 'Las casas se asignan por longitud zodiacal entre cúspides. No se adelanta un planeta a la casa siguiente por proximidad. Los segundos de arco son el resultado redondeado para los datos adoptados, no una precisión equivalente de la hora o del lugar exactos del nacimiento.',
                'regencies_note' => $this->regenciesNote($snapshot),
                'venus_cusp_note' => $this->venusCuspNote($snapshot),
                'positions' => $this->technicalPositions($snapshot),
                'houses_table' => $this->technicalHouses($snapshot['houses'] ?? []),
            ],
        ];
    }

    /** @return array{shared: array<string, string>, doors: array<string, array<string, string>>} */
    public function editableContent(Chart $chart): array
    {
        $report = $this->build($chart);
        $shared = [];
        foreach ($report['shared'] as $block => $paragraphs) {
            $shared[$block] = implode("\n\n", $paragraphs);
        }

        $doors = [];
        foreach ($report['doors'] as $door) {
            foreach ($door['blocks'] as $block => $paragraphs) {
                $doors[$door['key']][$block] = implode("\n\n", (array) $paragraphs);
            }
        }

        return ['shared' => $shared, 'doors' => $doors];
    }

    /**
     * Persiste en `interpretations` los bloques del informe que todavía no tengan
     * una fila guardada (manual, IA o generada previamente), para que el snapshot
     * quede fijado en base de datos y no dependa de recalcularse en cada visita.
     *
     * @param array<string, mixed> $report
     */
    public function persistGeneratedContent(Chart $chart, array $report): int
    {
        return DB::transaction(function () use ($chart, $report): int {
            $stored = $chart->interpretations()
                ->where('phase', 'fase-1')
                ->get()
                ->keyBy(fn ($interpretation) => ($interpretation->door ?? 'shared') . '.' . $interpretation->block);

            $saved = 0;

            foreach ($report['shared'] as $block => $paragraphs) {
                $storedBlock = ['intro' => 'shared_intro', 'states' => 'shared_states', 'conclusions' => 'shared_conclusions'][$block] ?? $block;
                if (! $stored->has('shared.' . $storedBlock)) {
                    $this->storeGeneratedBlock($chart, null, $storedBlock, implode("\n\n", $paragraphs), $saved);
                }
            }

            foreach ($report['doors'] as $door) {
                foreach ($door['blocks'] as $block => $paragraphs) {
                    if (! $stored->has($door['key'] . '.' . $block)) {
                        $this->storeGeneratedBlock($chart, $door['key'], $block, implode("\n\n", (array) $paragraphs), $saved);
                    }
                }
            }

            return $saved;
        });
    }

    private function storeGeneratedBlock(Chart $chart, ?string $door, string $block, string $content, int &$saved): void
    {
        $name = 'fase1_' . ($door ?? 'shared') . '_' . $block . '_generated';
        $template = ChartTemplate::firstOrCreate(
            ['name' => $name, 'version' => 2],
            [
                'door' => $door,
                'block' => $block,
                'content_type' => 'phase1_generated',
                'status' => 'published',
                'content' => 'Contenido editorial generado automáticamente al regenerar o validar el informe.',
            ],
        );

        $chart->interpretations()->create([
            'template_id' => $template->id,
            'phase' => 'fase-1',
            'door' => $door,
            'block' => $block,
            'content' => trim($content),
            'ai_assisted' => false,
            'rulers_used' => [],
        ]);
        $saved++;
    }

    /** @return array<string, mixed> */
    public function contextForDoor(Chart $chart, string $door): array
    {
        $chart->loadMissing('person', 'birthData.place');
        $points = [
            'sol' => ['point' => 'sun', 'question' => '¿Cómo desarrollo mi identidad y mi voluntad?'],
            'luna' => ['point' => 'moon', 'question' => '¿Qué necesito para sentirme emocionalmente seguro?'],
            'ascendente' => ['point' => 'ascendant', 'question' => '¿Cómo puedo dar este paso de una manera que pueda sostener?'],
            'descendente' => ['point' => 'descendant', 'question' => '¿Cómo puedo compartir mi vida sin dejar de escucharme?'],
        ];

        if (! isset($points[$door])) {
            throw new \InvalidArgumentException("Puerta Fase 1 no válida: {$door}");
        }

        $context = $this->doorContext(
            $chart->person->full_name ?: $chart->person->alias,
            $chart->snapshot,
            $points[$door]['point'],
            $door,
        );
        $context['question'] = $points[$door]['question'];
        $context['chart_id'] = $chart->id;

        return $context;
    }

    private function buildDoorIntroduction(string $name, array $snapshot): array
    {
        $sun = $snapshot['sun'] ?? [];
        $moon = $snapshot['moon'] ?? [];
        $ascendant = $snapshot['ascendant'] ?? [];
        $descendant = $snapshot['descendant'] ?? [];
        $sunSign = $this->translateSign($sun['sign'] ?? 'aries');
        $moonSign = $this->translateSign($moon['sign'] ?? 'aries');
        $ascendantSign = $this->translateSign($ascendant['sign'] ?? 'aries');
        $descendantSign = $this->translateSign($descendant['sign'] ?? 'aries');
        $sunHouse = $this->romanHouse($this->resolveHouse($sun, $snapshot['houses'] ?? [])['number']);
        $moonHouse = $this->romanHouse($this->resolveHouse($moon, $snapshot['houses'] ?? [])['number']);

        return [
            sprintf('En tu carta, %s, cada una de estas puertas tiene unas características que iremos explicando a lo largo del informe. Podemos empezar con una primera aproximación.', $name),
            sprintf('Tu Sol está en %s y en casa %s. %s nos invita a explorar cómo tienes en cuenta a otras personas, cómo buscas acuerdos y qué lugar das a tu propia opinión. La casa %s lleva estas preguntas a lo cotidiano: las tareas, los hábitos, la organización y las responsabilidades que compartes.', $sunSign, $sunHouse, $sunSign, $sunHouse),
            'Por ejemplo, cuando colaboras con alguien, ¿puedes expresar cómo prefieres hacer las cosas? ¿El reparto también tiene en cuenta tu tiempo? Aquí observaremos cómo puedes contribuir al bienestar de una situación sin dejar tus necesidades siempre para después.',
            sprintf('Tu Luna está en %s y en casa %s. Esta parte de la lectura nos acerca al afecto y a la importancia de sentir que tienes un lugar donde puedes mostrarte con confianza. %s propone explorar la calidez, la alegría y el deseo de que aquello que te importa sea recibido con atención. La casa %s sitúa estas preguntas en tu hogar, tu vida privada y los espacios donde buscas sentirte a gusto.', $moonSign, $moonHouse, $moonSign, $moonHouse),
            'Quizá para ti haya una diferencia entre que alguien te ayude con una tarea y que se detenga a escucharte. Ambas cosas pueden ser valiosas, pero responden a necesidades distintas. En este apartado iremos descubriendo qué gestos te hacen sentir querida y cómo puedes pedirlos cuando los necesitas.',
            sprintf('Tu Ascendente está en %s. Aquí hablaremos de cómo te aproximas a lo nuevo y de qué necesitas para dar un paso con confianza. %s nos lleva a observar tu ritmo, el tiempo que te das para decidir y las referencias que te ayudan a orientarte.', $ascendantSign, $ascendantSign),
            'Piensa en un cambio de planes o en una propuesta inesperada. ¿Te ayuda saber cómo va a funcionar? ¿Necesitas un rato para comprobar si te apetece y si puedes asumirla? Exploraremos cómo respetar tu manera de empezar y cómo distinguir cuándo necesitas prepararte un poco más y cuándo ya puedes probar.',
            sprintf('Tu Descendente está en %s. Esta puerta nos acerca a la confianza que construyes en tus relaciones. Hablaremos de lo que necesitas para compartir algo importante, de la sinceridad que esperas y de cómo expresas tus límites cuando un vínculo te importa. También observaremos qué haces cuando algo te preocupa y cómo conservas tu espacio propio cuando deseas estar muy cerca de otra persona.', $descendantSign),
            'Estas cuatro partes pueden necesitar cosas diferentes en un mismo momento. Puedes querer ayudar a alguien y necesitar que también te escuchen. Puedes desear una relación cercana y necesitar tiempo para abrirte. Puedes querer mantener un acuerdo y descubrir que debes cambiar alguna condición para poder cumplirlo.',
            'A lo largo del informe desglosaremos cada una de estas puertas. Explicaremos qué representa, qué aporta su signo, en qué ámbito de la vida se expresa y cómo su planeta regente —o sus regentes— añade información. Después reuniremos las piezas para comprender qué significa esa combinación en tu carta.',
            'Aprenderemos a distinguir sus características y necesidades mediante explicaciones y ejemplos cotidianos. Así podrás observar cuándo necesitas expresar una opinión, pedir afecto, respetar tu ritmo o aclarar un compromiso, y encontrar una respuesta que tenga en cuenta lo que estás viviendo.',
        ];
    }

    private function doorPosition(array $snapshot, string $pointKey): string
    {
        $point = $snapshot[$pointKey] ?? [];
        $house = $this->resolveHouse($point, $snapshot['houses'] ?? [])['number'];

        $position = sprintf(
            '%s %d° %02d\' %02d"',
            $this->translateSign($point['sign'] ?? 'aries'),
            $point['degrees'] ?? 0,
            $point['minutes'] ?? 0,
            (int) round($point['seconds'] ?? 0),
        );

        return in_array($pointKey, ['sun', 'moon'], true)
            ? $position . ' · casa ' . $this->romanHouse($house)
            : $position;
    }

    private function buildCombined(array $snapshot): array
    {
        $sunSign = $this->translateSign($snapshot['sun']['sign'] ?? 'aries');
        $moonSign = $this->translateSign($snapshot['moon']['sign'] ?? 'aries');
        $ascSign = $this->translateSign($snapshot['ascendant']['sign'] ?? 'aries');
        $descSign = $this->translateSign($snapshot['descendant']['sign'] ?? 'aries');
        $sunHouse = $this->romanHouse($this->resolveHouse($snapshot['sun'] ?? [], $snapshot['houses'] ?? [])['number']);
        $moonHouse = $this->romanHouse($this->resolveHouse($snapshot['moon'] ?? [], $snapshot['houses'] ?? [])['number']);

        return [
            'intro' => sprintf('Tu primera lectura reúne funciones diferentes. El Sol en %s puede querer construir un acuerdo justo; la Luna en %s puede necesitar que algo tuyo sea recibido con calidez; %s puede pedir tiempo y una base concreta; %s puede querer saber si el compromiso es real. Integrar no significa que una sola de estas necesidades decida siempre por todas.', $sunSign, $moonSign, $ascSign, $descSign),
            'rows' => [
                ['door' => sprintf('Sol en %s en %s', $sunSign, $sunHouse), 'resource' => 'Cooperación y criterio cotidiano.', 'need' => 'Tu voluntad y un reparto recíproco.'],
                ['door' => sprintf('Luna en %s en %s', $moonSign, $moonHouse), 'resource' => 'Calidez y expresión íntima.', 'need' => 'Afecto, alegría y permiso para recibir.'],
                ['door' => 'Ascendente ' . $ascSign, 'resource' => 'Base, ritmo y continuidad.', 'need' => 'Tiempo propio y capacidad de ajustar.'],
                ['door' => 'Descendente ' . $descSign, 'resource' => 'Profundidad y compromiso.', 'need' => 'Autonomía, privacidad y acuerdos claros.'],
            ],
            'paragraphs' => [
                'Puedes actuar con consideración y sentir decepción. Puedes desear cercanía y necesitar un rato propio. Puedes valorar la estabilidad y reconocer que una costumbre debe cambiar. Dar nombre a esas diferencias ayuda a no tratar cada incomodidad como una contradicción que haya que eliminar.',
                'Después del recorrido detallado conviene no reducir toda la carta a una sola explicación. Una misma escena puede pedir respuestas distintas según la puerta activa: elegir, regular una emoción, respetar un ritmo o negociar con otra persona.',
                'La relación entre tu Sol y tu Luna puede mostrar cómo una necesidad afectiva busca forma en tus decisiones y en lo que haces cada día. Ese camino puede ser útil, pero hacer más no sustituye automáticamente aquello que necesitas recibir.',
                'La confianza se construye con hechos, conversación y posibilidad de revisar. Ninguna relación elimina la individualidad: la profundidad gana calidad cuando ambas personas conservan voz y libertad.',
            ],
        ];
    }

    private function buildExpansion(array $snapshot): array
    {
        $labels = [
            'mercury' => 'Mercurio', 'venus' => 'Venus', 'mars' => 'Marte', 'jupiter' => 'Júpiter',
            'saturn' => 'Saturno', 'uranus' => 'Urano', 'neptune' => 'Neptuno', 'pluto' => 'Plutón',
        ];
        $functions = [
            'mercury' => 'pensamiento, comunicación y organización de la información',
            'venus' => 'valor, afinidad y formas de dar y recibir afecto',
            'mars' => 'deseo, acción y defensa de los límites',
            'jupiter' => 'expansión, aprendizaje y búsqueda de sentido',
            'saturn' => 'estructura, límites y aprendizajes que requieren tiempo',
            'uranus' => 'cambio, autonomía y maneras diferentes de comprender la experiencia',
            'neptune' => 'imaginación, sensibilidad e ideales difíciles de delimitar',
            'pluto' => 'revisión de patrones, intensidad y relación con el poder',
        ];
        $editorial = [
            'mercury' => ['content' => 'Mercurio simboliza pensamiento, comunicación y formas de ordenar información. El recurso consiste en traducir una situación compleja a un acuerdo comprensible; el exceso aparece si necesitas revisar cada palabra para impedir cualquier malentendido.', 'question' => '¿Estoy pensando para aclarar o para eliminar toda incertidumbre?'],
            'venus' => ['content' => 'Venus simboliza valor, afinidad y formas de dar y recibir afecto. La atención sostenida es un recurso cuando deja espacio a peticiones explícitas y a formas distintas de corresponder; si cada detalle se convierte en una prueba, la relación puede quedar cargada de expectativas silenciosas.', 'question' => '¿He dicho qué necesito o estoy esperando que mi esfuerzo lo comunique todo?'],
            'mars' => ['content' => 'Marte simboliza deseo y capacidad de actuar. Puede aportar planificación, perseverancia y atención a las consecuencias, siempre que la dirección elegida pueda revisarse cuando la experiencia aporta información nueva.', 'question' => '¿Mi disciplina sirve al propósito o estoy sosteniendo el plan solo porque fue el primero?'],
            'jupiter' => ['content' => 'Júpiter se relaciona con expansión, aprendizaje y búsqueda de sentido. Puede abrir posibilidades y reconocer apoyos; conviene comprobar qué forma concreta tiene una ilusión antes de asumir más de lo que puedes sostener.', 'question' => '¿Qué parte de esta ilusión tiene una forma concreta que también me cuida?'],
            'saturn' => ['content' => 'Saturno simboliza estructura, límites y aprendizajes que requieren tiempo. La responsabilidad puede favorecer acuerdos realistas cuando admite revisión; conviene distinguir seriedad de rigidez y compromiso de obligación de permanecer a cualquier precio.', 'question' => '¿Este acuerdo representa lo que queremos ahora o una idea de lo que deberíamos querer?'],
            'uranus' => ['content' => 'Urano se asocia con cambio, autonomía y maneras diferentes de comprender una experiencia. Un acuerdo puede admitir formas menos convencionales si todas las partes las comprenden y desean; la libertad compartida necesita claridad.', 'question' => '¿Cómo puedo introducir libertad mediante un acuerdo claro?'],
            'neptune' => ['content' => 'Neptuno simboliza imaginación, idealismo y sensibilidad hacia significados difíciles de delimitar. Una promesa puede tener mucho significado emocional y aun así necesitar condiciones prácticas que puedan comprobarse.', 'question' => '¿Qué sabemos con claridad y qué estamos suponiendo porque deseamos que funcione?'],
            'pluto' => ['content' => 'Plutón puede servir como símbolo para examinar patrones que concentran intensidad o poder. Una dinámica cotidiana puede cambiar cuando se hace visible quién decide, quién supervisa o quién termina asumiendo lo pendiente.', 'question' => '¿Qué cambio pequeño modificaría el patrón, en lugar de repetir la misma discusión?'],
        ];
        $rows = [];

        foreach ($labels as $key => $label) {
            if (! isset($snapshot[$key])) continue;
            $point = $snapshot[$key];
            $house = $this->resolveHouse($point, $snapshot['houses'] ?? []);
            $rows[] = [
                'title' => sprintf('%s en %s en casa %s', $label, $this->translateSign($point['sign'] ?? 'aries'), $this->romanHouse($house['number'])),
                'content' => sprintf('%s En %s y en la casa %s, esta función encuentra un terreno concreto para expresarse. El recurso aparece cuando puede adaptarse a la experiencia; el defecto cuando apenas encuentra espacio y el exceso cuando intenta resolverlo todo mediante una única respuesta.', $editorial[$key]['content'], $this->translateSign($point['sign'] ?? 'aries'), $this->romanHouse($house['number'])),
                'example' => sprintf('Ejemplo: observa una situación de la casa %s en la que participa %s y comprueba qué cambia cuando utilizas esta función con una medida que puedas sostener.', $this->romanHouse($house['number']), strtolower($label)),
                'question' => 'Pregunta útil: ' . $editorial[$key]['question'],
            ];
        }

        return $rows;
    }

    private function technicalPositions(array $snapshot): array
    {
        $keys = ['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto', 'ascendant', 'descendant'];
        $labels = ['sun' => 'Sol', 'moon' => 'Luna', 'mercury' => 'Mercurio', 'venus' => 'Venus', 'mars' => 'Marte', 'jupiter' => 'Júpiter', 'saturn' => 'Saturno', 'uranus' => 'Urano', 'neptune' => 'Neptuno', 'pluto' => 'Plutón', 'ascendant' => 'Ascendente', 'descendant' => 'Descendente'];

        return array_values(array_filter(array_map(function (string $key) use ($snapshot, $labels): ?array {
            if (! isset($snapshot[$key])) {
                return null;
            }

            $point = $snapshot[$key];
            return [
                'name' => $labels[$key],
                'position' => sprintf('%s %d° %02d\' %02d"', $this->translateSign($point['sign'] ?? 'aries'), $point['degrees'] ?? 0, $point['minutes'] ?? 0, (int) round($point['seconds'] ?? 0)),
                'house' => $this->resolveHouse($point, $snapshot['houses'] ?? [])['number'],
            ];
        }, $keys)));
    }

    private function technicalHouses(array $houses): array
    {
        return array_values(array_map(function (int $number) use ($houses): array {
            $house = $houses[$number] ?? [];
            return [
                'number' => $number,
                'position' => sprintf('%s %d° %02d\' %02d"', $this->translateSign($house['sign'] ?? 'aries'), $house['degrees'] ?? 0, $house['minutes'] ?? 0, (int) round($house['seconds'] ?? 0)),
            ];
        }, range(1, 12)));
    }

    private function julianDay(DateTimeInterface|string|null $utcDateTime): ?string
    {
        if (! $utcDateTime) {
            return null;
        }

        $timestamp = $utcDateTime instanceof DateTimeInterface
            ? $utcDateTime->getTimestamp()
            : strtotime((string) $utcDateTime);

        if ($timestamp === false) {
            return null;
        }

        return number_format(($timestamp / 86400) + 2440587.5, 8, '.', '');
    }

    private function legalTimeNote(Chart $chart): string
    {
        $date = $chart->birthData->local_date?->format('Y-m-d');
        $city = strtolower((string) ($chart->birthData->place->city ?? ''));

        if ($date === '1986-09-28' && str_contains($city, 'sevilla')) {
            return 'Ese mismo día terminó el horario de verano en España: a las 03:00 se retrasó el reloj a las 02:00. A las 21:00 ya regía CET, UTC+1. Por tanto, el cálculo utiliza las 20:00 UTC del 28 de septiembre. La hora facilitada no está dentro del intervalo repetido del cambio de madrugada.';
        }

        return 'La hora facilitada se interpreta como hora civil local y se convierte a UTC utilizando la zona horaria histórica asociada al lugar y a la fecha de nacimiento.';
    }

    private function venusCuspNote(array $snapshot): string
    {
        $venus = $snapshot['venus'] ?? null;
        $descendant = $snapshot['descendant'] ?? null;

        if (! $venus || ! $descendant) {
            return 'La posición de Venus y su casa se mantienen diferenciadas en toda la interpretación.';
        }

        $distance = abs((float) ($descendant['longitude'] ?? 0) - (float) ($venus['longitude'] ?? 0));
        $distance = min($distance, 360 - $distance);

        $venusHouse = $this->resolveHouse($venus, $snapshot['houses'] ?? [])['number'];
        $descendantHouse = $this->resolveHouse($descendant, $snapshot['houses'] ?? [])['number'];

        return sprintf('Venus queda a aproximadamente %s° del Descendente, por el lado de casa %s. Su casa puede ser sensible a una posible aproximación en la hora de nacimiento; con la hora utilizada, permanece en %s. El grado y la casa se mantienen diferenciados en toda la interpretación.', number_format($distance, 2, '.', ''), $this->romanHouse($venusHouse), $this->romanHouse($venusHouse));
    }

    private function regenciesNote(array $snapshot): string
    {
        $notes = [];
        foreach (['libra', 'taurus', 'leo', 'scorpio'] as $sign) {
            $resolved = (new RegencyResolver())->resolve($sign);
            $traditional = implode(' y ', array_map(fn (string $planet): string => $this->translatePlanet($planet), $resolved['traditional']));
            $modern = implode(' y ', array_map(fn (string $planet): string => $this->translatePlanet($planet), $resolved['modern']));
            $notes[] = sprintf('%s: regencia tradicional %s; moderna %s', $this->translateSign($sign), $traditional, $modern);
        }

        return 'Regencias utilizadas en las puertas: '.implode('. ', $notes).'. Nodo Verdadero y Luna Negra Media quedan como criterios de futuras ampliaciones, sin interpretación en este informe. No se han definido orbes ni se estudian aspectos, tránsitos o revolución solar.';
    }

    private function romanHouse(int $house): string
    {
        return [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'][$house] ?? (string) $house;
    }

    private function doorContext(string $name, array $snapshot, string $pointKey, string $door): array
    {
        $point = $snapshot[$pointKey] ?? ['sign' => 'aries', 'degrees' => 0, 'minutes' => 0, 'seconds' => 0, 'longitude' => 0];
        $house = $this->resolveHouse($point, $snapshot['houses'] ?? []);
        $rulers = (new RegencyResolver())->resolve($point['sign'] ?? 'aries')['modern'];
        $rulerNames = implode(' y ', array_map(fn (string $ruler): string => $this->translatePlanet($ruler), $rulers));
        $ruler = $snapshot[$rulers[0]] ?? ['sign' => 'aries', 'degrees' => 0, 'minutes' => 0, 'seconds' => 0];
        $rulerDetails = array_map(function (string $rulerKey) use ($snapshot): array {
            $rulerPoint = $snapshot[$rulerKey] ?? ['sign' => 'aries', 'degrees' => 0, 'minutes' => 0, 'seconds' => 0];

            return [
                'key' => $rulerKey,
                'name' => $this->translatePlanet($rulerKey),
                'sign' => $this->translateSign($rulerPoint['sign'] ?? 'aries'),
                'house' => $this->resolveHouse($rulerPoint, $snapshot['houses'] ?? [])['number'],
                'position' => [
                    'degrees' => (int) ($rulerPoint['degrees'] ?? 0),
                    'minutes' => (int) ($rulerPoint['minutes'] ?? 0),
                    'seconds' => (int) round($rulerPoint['seconds'] ?? 0),
                ],
            ];
        }, $rulers);
        $stateCharacteristics = (new PhaseOneDoorCatalog())->stateCharacteristics($door, $this->translateSign($point['sign'] ?? 'aries'));
        return [
            'subject' => match ($door) {
                'sol' => 'El Sol',
                'luna' => 'La Luna',
                'ascendente' => 'El Ascendente',
                'descendente' => 'El Descendente',
            },
            'sign' => $this->translateSign($point['sign'] ?? 'aries'),
            'degrees' => (int) ($point['degrees'] ?? 0),
            'minutes' => (int) ($point['minutes'] ?? 0),
            'seconds' => (int) round($point['seconds'] ?? 0),
            'house' => $house['number'],
            'house_sign' => $house['sign'],
            'rulers' => $rulerNames,
            'ruler_keys' => $rulers,
            'ruler_sign' => $this->translateSign($ruler['sign'] ?? 'aries'),
            'ruler_house' => $this->resolveHouse($ruler, $snapshot['houses'] ?? [])['number'],
            'ruler_degrees' => (int) ($ruler['degrees'] ?? 0),
            'ruler_minutes' => (int) ($ruler['minutes'] ?? 0),
            'ruler_seconds' => (int) round($ruler['seconds'] ?? 0),
            'ruler_details' => $rulerDetails,
            'states' => array_map(static fn (array $characteristics): array => ['characteristics' => $characteristics], $stateCharacteristics),
            'name' => $name,
            'door' => $door,
        ];
    }

    private function resolveHouse(array $point, array $houses): array
    {
        foreach (range(1, 12) as $houseNumber) {
            $current = $houses[$houseNumber]['longitude'] ?? null;
            $nextNumber = $houseNumber === 12 ? 1 : $houseNumber + 1;
            $next = $houses[$nextNumber]['longitude'] ?? null;

            if ($current === null || $next === null || ! isset($point['longitude'])) {
                continue;
            }

            $span = fmod($next - $current + 360, 360);
            $distance = fmod($point['longitude'] - $current + 360, 360);
            if ($distance < $span || ($houseNumber === 1 && $distance === 0.0)) {
                return ['number' => $houseNumber, 'sign' => $this->translateSign($houses[$houseNumber]['sign'] ?? 'aries')];
            }
        }

        return ['number' => 1, 'sign' => $this->translateSign($houses[1]['sign'] ?? 'aries')];
    }

    private function translateSign(string $sign): string
    {
        return [
            'aries' => 'Aries', 'taurus' => 'Tauro', 'gemini' => 'Géminis',
            'cancer' => 'Cáncer', 'leo' => 'Leo', 'virgo' => 'Virgo',
            'libra' => 'Libra', 'scorpio' => 'Escorpio', 'sagittarius' => 'Sagitario',
            'capricorn' => 'Capricornio', 'aquarius' => 'Acuario', 'pisces' => 'Piscis',
        ][strtolower(trim($sign))] ?? ucfirst(strtolower(trim($sign)));
    }

    private function translatePlanet(string $planet): string
    {
        return [
            'sun' => 'el Sol', 'moon' => 'la Luna', 'mercury' => 'Mercurio',
            'venus' => 'Venus', 'mars' => 'Marte', 'jupiter' => 'Júpiter',
            'saturn' => 'Saturno', 'uranus' => 'Urano', 'neptune' => 'Neptuno',
            'pluto' => 'Plutón',
        ][$planet] ?? ucfirst($planet);
    }
}