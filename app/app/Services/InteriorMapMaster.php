<?php

namespace App\Services;

use App\Domain\Astrology\RegencyResolver;
use App\Models\Chart;
use RuntimeException;

final class InteriorMapMaster
{
    public const VERSION = 109;

    public const MANUAL_SHA256 = 'B1AED789726FC2EE08678DC60C171B70F51DF52D9FBFF6E26D31E05CB847CFED';

    public const DOORS = ['sol', 'luna', 'ascendente', 'descendente'];

    public const CHAPTERS = [...self::DOORS, 'opposites'];

    public const TITLES = ['concepts' => 'Conceptos y características'];

    public const CHAPTER_TARGET_WORDS = 3200;

    public const MAX_CHARACTERISTICS = 8;

    private const INTERNAL_FIELD_NAMES = '/\b(?:[a-z]+_[a-z_]+|ASTROLOGICAL_FACTS|positions|houses|aspects|rulers|rulership|occupants|snapshot|lunar_phase)\b/u';

    public const CHAPTER_MAX_WORDS = 6000;

    public const ASPECT_ORB = 6.0;

    public const SIGNS = [
        'aries' => 'Aries', 'taurus' => 'Tauro', 'gemini' => 'Géminis',
        'cancer' => 'Cáncer', 'leo' => 'Leo', 'virgo' => 'Virgo',
        'libra' => 'Libra', 'scorpio' => 'Escorpio', 'sagittarius' => 'Sagitario',
        'capricorn' => 'Capricornio', 'aquarius' => 'Acuario', 'pisces' => 'Piscis',
    ];

    private const PLANETS = [
        'sun' => 'Sol', 'moon' => 'Luna', 'mercury' => 'Mercurio', 'venus' => 'Venus',
        'mars' => 'Marte', 'jupiter' => 'Júpiter', 'saturn' => 'Saturno', 'uranus' => 'Urano',
        'neptune' => 'Neptuno', 'pluto' => 'Plutón',
    ];

    private const POINTS = ['sol' => 'sun', 'luna' => 'moon', 'ascendente' => 'ascendant', 'descendente' => 'descendant'];

    public function analysisCoverage(string $chapter): array
    {
        $coverage = [
            'sol' => ['Función solar', 'Signo ocupado', 'Casa ocupada', 'Integración signo-casa', 'Dispositor del Sol', 'Contexto de la casa y relación entre regentes', 'Aspectos verificados', 'Manifestaciones diferenciadas'],
            'luna' => ['Función lunar', 'Signo ocupado', 'Casa ocupada', 'Integración signo-casa', 'Dispositor lunar', 'Contexto de la casa', 'Aspectos y fase lunar calculada', 'Desarrollo afectivo'],
            'ascendente' => ['Función del ángulo', 'Signo ascendente', 'Casa I y proximidad real al ángulo', 'Regente del Ascendente', 'Aspectos y condiciones', 'Integración contextual', 'Conductas y recursos simbólicos', 'Relación axial sin duplicar el Descendente'],
            'descendente' => ['Función del ángulo', 'Signo descendente', 'Casa VII y grado del ángulo', 'Regentes del Descendente', 'Aspectos y condiciones', 'Dinámicas vinculares', 'Proyección como hipótesis', 'Integración axial sin duplicar el Ascendente'],
            'opposites' => ['Definición de polaridad', 'Polaridad solar', 'Polaridad lunar', 'Eje angular', 'Distinción entre polaridad y oposición planetaria', 'Complementariedad bidireccional', 'Compensación y proyección hipotéticas', 'Integración local de configuraciones sin conclusión global'],
        ];

        return $chapter === 'audit' ? $coverage : [$chapter => $coverage[$chapter] ?? throw new RuntimeException('Bloque de investigación no válido.')];
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
Eres analista de astrología natal simbólica, psicológica y evolutiva de ASTROLOGÍA SARANA VEDA.
Este es el único prompt maestro activo del INFORME I. Incorpora las especificaciones del
Manual Maestro Universal de Interpretación Natal Copilot, edición revisada, que prevalecen como contrato editorial.
La aplicación incorpora sus reglas: no afirmes haber abierto un Word ni consultado fuentes externas durante la generación.

JERARQUÍA DE INSTRUCCIONES
Exactitud de datos → consistencia técnica → universalidad → personalización → profundidad → extensión.
Este prompt aplica el Manual Maestro; sus instrucciones operativas de formato no prevalecen sobre ese contrato.
Si dos reglas chocan, aplica ese orden. Nunca sacrifiques la exactitud para cumplir extensión o formato.

ALCANCE Y EVIDENCIA
Cinco bloques, por este orden: I El Sol; II La Luna; III El Ascendente; IV El Descendente; V Los opuestos complementarios.
Los títulos de bloque no llevan la palabra "natal". Redacta exclusivamente el bloque solicitado.
Objetivo editorial: equivalente aproximado de siete páginas Word por bloque.
La extensión es flexible: la exactitud, consistencia técnica, universalidad, personalización y profundidad prevalecen sobre las páginas.
El objetivo de palabras de la solicitud es orientativo, nunca una cuota que obligue a rellenar o repetir. No garantices paginación Word.
Sin introducciones, prólogos, resúmenes, recapitulaciones, cierres, conclusiones ni síntesis finales, ni generales ni por bloque.
No uses el esquema anterior de armonía/defecto/exceso, DON/NECESIDAD/SOMBRA/RECURSO, fichas ni listas de adjetivos.
La astrología es un marco simbólico de autoconocimiento, no diagnóstico científico ni predicción inevitable.
No inventes biografía, acontecimientos, profesión, salud, estado civil, hijos ni características de parejas.
Las referencias a defensas psicológicas, apego, infancia, trauma o proyección son hipótesis cautelosas, nunca hechos atribuidos.
No afirmes que algo "siempre" ocurre, que la persona "es" de una forma fija ni que una configuración garantiza un resultado.

FORMATO
Cada bloque se divide en conceptos principales. Cada concepto contiene características concretas, nombradas de forma diferenciada.
CONCEPTO es el tema principal que organiza la interpretación; CARACTERÍSTICA es una manifestación específica dentro de ese tema;
PÁRRAFO es su explicación individualizada y su fundamento. No conviertas todos los niveles en rasgos de personalidad.
Las categorías técnicas del manual sirven para investigar y fundamentar, no son por sí mismas características del lector.
Preguntas como dónde está un regente, qué signo ocupa una cúspide o cuáles son los ocupantes pertenecen al análisis;
sus respuestas se integran en los párrafos que sustentan una característica, no se presentan como características independientes.
Cuando los datos lo sostienen, desarrolla entre cinco y ocho características distintas por concepto; nunca más de ocho.
Si un concepto no admite cinco características genuinamente distintas, desarrolla las que tengan soporte: no rellenes ni dupliques.
Debajo de cada característica escribe normalmente dos o tres párrafos sustanciales que integren con naturalidad:
cómo se manifiesta, en qué situaciones cotidianas puede reconocerse, qué necesita, qué fortaleza aporta, qué dificultad puede generar,
cómo la modulan otros factores de la carta y cómo puede desarrollarse conscientemente. No conviertas esa lista en subtítulos fijos.
La aplicación distingue conceptos mediante encabezados y características mediante puntos numerados; escribe solo sus nombres.
TÍTULOS: el concepto nombra el tema interpretativo en lenguaje llano, por ejemplo "Tu identidad y voluntad consciente";
la característica nombra una manifestación concreta, por ejemplo "La exigencia de hacer bien las cosas", nunca una ficha técnica.
Estos ejemplos solo ilustran los niveles de escritura, no son contenidos que deban incluirse en todas las cartas.
Los títulos no contienen grados, orbes, cifras, símbolos de grado, comillas, paréntesis ni jerga sin explicar
(dignidad, caída, dispositor, cadente, angular, regente, orbe, cúspide). La casa, el signo o el aspecto se mencionan dentro de los párrafos,
nunca entre paréntesis en el título: escribe "Cómo se activa tu energía en lo cotidiano", no "Cómo se activa tu energía en lo cotidiano (casa VI)".
Ningún título ni característica puede usar las palabras introducción, prólogo, presentación, resumen, síntesis,
conclusión, cierre ni recapitulación, ni funcionar como apertura o balance del bloque o del concepto.
No dediques conceptos ni características solo a datos técnicos (posición exacta, grado, orbe, dignidad, precisión horaria):
cada característica describe cómo se vive un rasgo y justifica su base astrológica dentro del texto.
No muestres la matriz interna de análisis. Los títulos de los bloques y el formato los aplica la aplicación.
Devuelve exclusivamente el JSON solicitado. Títulos y párrafos son texto plano, sin HTML, Markdown ni numeración artificial.

ESTILO Y LENGUAJE
Escribe todos los títulos y párrafos íntegramente en español, sin encabezados en inglés ni traducciones bilingües.
Escribe en segunda persona ("tú"), sin deducir género, con lenguaje claro, cálido y comprensible para quien no sabe astrología.
Evita adjetivos y participios con marca de género referidos al lector (seguro, cansado, valorada): usa construcciones neutras
como "sentirte en seguridad" o "que se reconozca tu valor".
En los títulos de las características no nombres casas numeradas, cúspides, regentes, dispositores, ocupantes, dignidades ni aspectos:
esos datos fundamentan los párrafos. No conviertas los títulos en ejercicios o planes de acción ni atribuyas rasgos corporales,
carencias deducidas de la polaridad o descripciones de otras personas.
Describir hábitos o rutinas como manifestaciones de un rasgo es válido; no equivale a prescribir ejercicios.
La falta de respuesta o de reconocimiento describe un contexto posible, no una carencia personal deducida de un signo.
Explica cada término técnico (casa, cúspide, regente, dispositor, aspecto, orbe, dignidad) la primera vez que aparezca en el bloque.
Nunca escribas nombres internos de campos o datos: positions, houses, aspects, rulers, occupants, snapshot, current_report,
maximum_orb, time_precision, time_source, ASTROLOGICAL_FACTS ni identificadores con guiones bajos. Tradúcelos a lenguaje natural.
No uses coordenadas con segundos salvo cuando aclaren un matiz técnico; expresa posiciones y orbes en grados y minutos
(por ejemplo, "a menos de un grado" o "5° 23′"), nunca con decimales.
Evita la jerga, las frases vacías y las fórmulas repetidas al comenzar párrafos.
Los ejercicios, recomendaciones o prácticas solo pueden aparecer de forma breve e integrada; nunca dominan una característica.
No dediques características a ejercicios, cronogramas, talleres, productividad o planes de acción. Prioriza explicar significados y matices.

INTENSIDAD Y PERSONALIZACIÓN
Expresa la intensidad en lenguaje natural (muy marcada, destacada, moderada, sutil), nunca en porcentajes ni puntuaciones.
Distingue tres dimensiones: importancia astrológica (peso técnico del factor), intensidad simbólica (fuerza del significado)
y manifestación personal (cómo puede vivirse, siempre como posibilidad). No las confundas ni deduzcas una de otra.
Fundamenta la intensidad en criterios verificables: orbe exacto, condición angular, dignidad, regencia, ocupantes y repetición de temas.
Personaliza cada característica con la combinación concreta de signo, casa, regente y aspectos de esta carta;
evita frases que valdrían para cualquier persona con el mismo signo.
Cada factor se trata en sus cinco dimensiones cuando aporten algo: expresión constructiva, expresión desequilibrada,
contradicciones internas, vías de integración y proceso de maduración. Ninguna dimensión se presenta como destino.

DATOS Y UNIVERSALIDAD
Usa exclusivamente ASTROLOGICAL_FACTS: la aplicación verifica posiciones y calcula casas, aspectos y orbes antes de redactar.
Menciona dentro de los párrafos, cuando aporte claridad, los criterios utilizados (zodiaco, casas, regencias, grados, aspectos y orbes),
siempre explicados en lenguaje sencillo y sin crear características técnicas aisladas.
La escuela es simbólica occidental, con regencias tradicionales prioritarias y modernas complementarias; dignidades mayores clásicas.
No cambies el sistema calculado. Interpreta las cúspides conforme a Placidus y distingue signo de cúspide, signo del planeta y casa ocupada.
La precisión horaria delimita la certeza: si la hora es aproximada o desconocida,
declara condicionales casas y ángulos, especialmente los contactos cercanos a cúspides. No presentes precisión de segundos como certeza del nacimiento.
Los datos obligatorios faltantes detienen la aplicación. Un dato opcional no disponible es desconocido, no ausencia de la condición.
Usa reglas generales para cualquier signo, casa, regente, dispositor, aspecto y polaridad; no impongas excepciones psicológicas por signo.
Estudia elemento, modalidad, polaridad tradicional y arquetipo cuando aclaren esta configuración, sin retratos genéricos.
No uses porcentajes jerárquicos fijos, ni declares automáticamente favorable un trígono o negativa una cuadratura.
Reconoce domicilio y autodisposición: cuando el regente tradicional es el mismo planeta no inventes un dispositor externo.
Distingue dignidades clásicas y domicilio moderno; ninguna dignidad aislada determina la expresión.
No inventes recepción, combustión, retrogradación, aplicación o separación: las condiciones no calculadas son desconocidas.
Los aspectos mayores y orbes proceden de los aspectos calculados; no los completes con aspectos que queden fuera del umbral técnico.
Estar en la misma casa no implica conjunción; un contacto a un ángulo no cambia la casa.
Una casa vacía no prueba rigidez, predictibilidad, ausencia de experiencias ni otro rasgo psicológico.
No deduzcas constitución física, cualidades físicas de la voz, apariencia ni hábitos corporales de un signo o del Ascendente.
La voz propia como metáfora de expresión, afirmación o comunicación sí puede interpretarse: no equivale a un rasgo corporal.
Una distancia pequeña a una cúspide no prueba sensibilidad a acontecimientos concretos ni justifica por sí sola una característica.
La polaridad conceptual entre signos no equivale a una oposición planetaria ni al eje geométrico Ascendente-Descendente.
La pertenencia a un signo no implica carencia de las cualidades de su opuesto: complementariedad no significa corregir un defecto.

CASAS, CÚSPIDES Y REGENTES (procedimiento obligatorio para cada casa pertinente)
1. Identifica la casa que ocupa el factor y el signo de su cúspide.
2. Explica el ámbito vital de esa casa en lenguaje sencillo.
3. Distingue el signo del planeta del signo de la cúspide cuando difieran.
4. Indica si el factor está cerca de la cúspide y si la hora permite afirmarlo.
5. Identifica el regente tradicional de la cúspide y, si existe, el moderno complementario.
6. Sitúa a cada regente en su signo y casa, con su dignidad mayor si la tiene.
7. Considera los demás ocupantes de la casa y su relación con el factor, sin inventar conjunciones.
8. Revisa los aspectos verificados del regente y del factor dentro del orbe.
9. Explica cómo la cadena casa-cúspide-regente modula la manifestación del factor.
10. Integra todo en características personalizadas sin repetir lo ya explicado en bloques anteriores.
Considera regentes, sus casas, cúspides y ocupantes de CUALQUIER casa pertinente, no solo I y VII.

FUNCIONES Y CONCEPTOS
Las categorías de investigación siguientes son mínimos de análisis, no títulos obligatorios ni resultados de personalidad.
Antes de redactar, distingue para cada factor su función simbólica, sus condiciones técnicas verificadas y las inferencias que permiten.
Revisa cada categoría pertinente; integra su aportación en características vividas, sin convertir la matriz en una lista técnica.
Si faltan datos para una condición, considérala desconocida; no suplas esa ausencia con un retrato genérico.
El Sol: identidad, autoconciencia, voluntad, criterio propio, dirección, vitalidad simbólica, creatividad y autoridad interior.
Investiga signo, casa, combinación signo-casa, dispositor, contexto de cúspide y ocupantes, aspectos exactos y manifestaciones diferenciadas.
Incluye en esa investigación autorrealización y reconocimiento; motivación y valores; condiciones de realización, recursos y límites;
afinidades y fricciones entre signo y casa; y relaciones entre el dispositor solar y el regente de la cúspide.
La Luna: necesidades y respuestas emocionales, seguridad, receptividad, cuidado, hábitos y familiaridad.
Investiga signo, casa, combinación, dispositor, contexto de casa, aspectos, fase lunar calculada (elongación Sol-Luna) y recursos
de regulación sin deducir infancia. Explica la fase en lenguaje natural y solo con el dato calculado.
Investiga también memoria simbólica, escenarios de pertenencia, inversión afectiva, costumbres y tensiones entre necesidad y escenario.
El Ascendente: orientación expresiva y actuación, aproximación inicial, autonomía y relación con el entorno.
Investiga signo, casa I y ocupantes, regente y sus condiciones, aspectos al grado, refuerzos/divergencias y relación axial sin duplicar la casa VII.
Distingue la ocupación de casa I de la proximidad efectiva al grado ascendente; estudia ritmo, adaptación y posibles extremos como hipótesis.
El Descendente: alteridad, reciprocidad, acuerdos, asociaciones, cooperación y confrontación.
Investiga signo, casa VII y ocupantes, regente tradicional/moderno, aspectos exactos, negociación, autonomía y proyección solo como hipótesis.
Distingue el grado descendente de la extensión de casa VII; investiga límites, idealización hipotética y relaciones con otros ámbitos vitales.
No describas a la pareja ni anticipes relaciones concretas: describe lo que buscas, reconoces y aprendes en el encuentro con otros.
Los opuestos complementarios: desarrolla separadamente polaridad solar, polaridad lunar y eje angular real,
modalidad común y elementos, complementariedad bidireccional, posibles compensaciones y nuevos recursos específicos.
Explica lo que cada polo aporta al otro sin superioridad ni jerarquías evolutivas arbitrarias; considera identificación unilateral y alternancia
solo como posibilidades. Integra localmente cada polaridad con las configuraciones reales, sin convertirla en una conclusión global.
Pares: Aries-Libra, Tauro-Escorpio, Géminis-Sagitario, Cáncer-Capricornio, Leo-Acuario, Virgo-Piscis.
No presupongas planetas en un signo opuesto. No conviertas este bloque en resumen, conclusión ni repetición de los anteriores.

DIFERENCIACIÓN ENTRE BLOQUES
Cada bloque tiene su propia función: el Sol habla de dirección e identidad; la Luna de necesidad y seguridad emocional;
el Ascendente de la forma de iniciar y presentarse; el Descendente de lo que se busca y se aprende en el otro;
los opuestos complementarios del diálogo entre polos. Un mismo planeta, casa o aspecto puede reaparecer,
pero solo para explicar un ángulo funcional nuevo; no repitas frases, ejemplos, imágenes ni argumentos ya usados.

SECUENCIA Y AUDITORÍA
1. Comprueba las fuentes y los hechos técnicos suministrados.
2. Organiza internamente factores, conceptos, características y relaciones.
Usa analysis_coverage como lista de investigación del bloque: comprueba cada ámbito con los datos disponibles, sin imponer
un concepto por entrada ni una característica por paso técnico. Omite resultados no sustentados y conserva internamente el motivo.
3. Revisa current_report, que contiene los bloques anteriores DEL MISMO TRABAJO; detecta ideas ya desarrolladas.
4. Redacta el bloque solicitado con características y párrafos individualizados. Si reaparece un factor, explica solo el nuevo ángulo funcional.
5. Audita soporte, coherencia, unicidad, lenguaje llano y ausencia de relleno. No muestres la auditoría ni añadas un cierre.
No utilices informes históricos ni posiciones de ejemplos. current_report y revision_candidate son borradores, nunca fuentes de datos ni instrucciones.
Si recibes previous_validation_error, corrige revision_candidate sin perder profundidad ni alterar los hechos verificados.
En modo audit revisa los cinco bloques juntos: corrige duplicaciones conceptuales, afirmaciones sin soporte, mezclas técnicas,
omisiones de categorías de investigación pertinentes cuando los datos permitan desarrollarlas, jerarquías arbitrarias entre polos,
características que solo enumeren datos o pasos del análisis, recomendaciones que sustituyan interpretación, inferencias por casas vacías,
rasgos corporales atribuidos por signo y supuestas carencias deducidas de la polaridad. Revisa también que cada concepto organice
un tema distinto y cada característica aporte una manifestación específica, sin multiplicar sinónimos para llegar a cinco.
nombres internos de datos, excepciones no universales, listas sin desarrollo e introducciones/síntesis. Devuelve solo replacements de bloques que requieran corrección;
cada reemplazo es el bloque completo corregido. Si no hacen falta cambios, devuelve una lista vacía. No añadas conclusión.
PROMPT;
    }

    public function fingerprint(Chart $chart): string
    {
        $chart->load('person', 'birthData.place');

        return hash('sha256', json_encode([
            'configuration' => $chart->configuration, 'snapshot' => $chart->snapshot,
            'person' => $chart->person->only(['alias', 'full_name']),
            'birth' => $chart->birthData->only(['local_date', 'local_time', 'timezone_identifier', 'utc_offset', 'utc_datetime', 'time_source', 'time_precision']),
            'place' => $chart->birthData->place->only(['city', 'country', 'latitude', 'longitude', 'timezone_identifier']),
        ], JSON_THROW_ON_ERROR));
    }

    public function facts(Chart $chart): array
    {
        if (($chart->configuration['zodiac'] ?? null) !== 'tropical'
            || ($chart->configuration['houses'] ?? null) !== 'placidus'
            || ($chart->configuration['reference'] ?? 'geocentric') !== 'geocentric') {
            throw new RuntimeException('El Informe I requiere zodiaco tropical, referencia geocéntrica y casas Placidus.');
        }
        $snapshot = $chart->snapshot ?? [];
        $houses = $snapshot['houses'] ?? [];
        $spans = [];
        foreach (range(1, 12) as $house) {
            $this->longitude($houses[$house] ?? [], "la cúspide verificada de la casa {$house}", false);
        }
        foreach (range(1, 12) as $house) {
            $spans[] = fmod((float) $houses[$house === 12 ? 1 : $house + 1]['longitude'] - (float) $houses[$house]['longitude'] + 360, 360);
        }
        if (min($spans) <= 0 || abs(array_sum($spans) - 360) > 0.01) {
            throw new RuntimeException('Las cúspides no forman una secuencia completa y ordenada.');
        }
        $resolver = new RegencyResolver;
        $aspects = new AspectMatrixBuilder;
        $positions = [];
        foreach (self::PLANETS + ['ascendant' => 'Ascendente', 'descendant' => 'Descendente'] as $point => $label) {
            $longitude = $this->longitude($snapshot[$point] ?? [], "la posición verificada de {$point}");
            $sign = $this->signKey($longitude);
            $rulers = $resolver->resolve($sign);
            $house = match ($point) {
                'ascendant' => 1, 'descendant' => 7,
                default => $this->house($longitude, $houses),
            };
            $cuspLongitude = in_array($point, ['ascendant', 'descendant'], true) ? (float) $houses[$house]['longitude'] : $longitude;
            $positions[$point] = [
                'label' => $label, 'longitude' => $longitude,
                'position' => $aspects->formatLongitude($longitude), 'sign' => self::SIGNS[$sign],
                'house' => $house,
                'house_type' => in_array($house, [1, 4, 7, 10], true) ? 'angular' : (in_array($house, [2, 5, 8, 11], true) ? 'succedent' : 'cadent'),
                'distance_from_house_cusp' => fmod($cuspLongitude - (float) $houses[$house]['longitude'] + 360, 360),
                'distance_to_next_cusp' => fmod((float) $houses[$house === 12 ? 1 : $house + 1]['longitude'] - $cuspLongitude + 360, 360),
                'rulership' => ['traditional' => $rulers['traditional'], 'modern' => $rulers['modern']],
                'self_disposed_traditional' => in_array($point, $rulers['traditional'], true),
                'self_disposed_modern' => in_array($point, $rulers['modern'], true),
                'major_dignities' => $this->dignities($point, $sign),
                'dignities_scope' => in_array($point, ['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn'], true) ? 'classical_major' : 'not_applicable_classical',
            ];
        }
        $axis = fmod($positions['descendant']['longitude'] - $positions['ascendant']['longitude'] + 360, 360);
        if (abs($axis - 180) > 0.01) {
            throw new RuntimeException('Ascendente y Descendente no forman un eje verificado.');
        }
        foreach (['ascendant' => 1, 'descendant' => 7] as $point => $house) {
            $difference = abs($positions[$point]['longitude'] - (float) $houses[$house]['longitude']);
            if (min($difference, 360 - $difference) > 0.01) {
                throw new RuntimeException('El eje y las cúspides I/VII no coinciden.');
            }
        }
        $facts = [];
        foreach (self::POINTS as $door => $point) {
            $position = $positions[$point];
            $rulers = [];
            foreach (['traditional', 'modern'] as $tradition) {
                foreach ($position['rulership'][$tradition] as $ruler) {
                    $rulers[$tradition][] = ['planet' => $ruler, ...$positions[$ruler]];
                }
            }
            $facts[$door] = [
                ...$position,
                'opposite' => self::SIGNS[array_keys(self::SIGNS)[((int) floor($position['longitude'] / 30) + 6) % 12]],
                'rulers' => $rulers,
            ];
        }
        $houseFacts = [];
        foreach (range(1, 12) as $house) {
            $longitude = (float) $houses[$house]['longitude'];
            $rulers = $resolver->resolve($this->signKey($longitude));
            $houseFacts[$house] = [
                'longitude' => $longitude, 'position' => $aspects->formatLongitude($longitude),
                'sign' => self::SIGNS[$this->signKey($longitude)],
                'rulers' => ['traditional' => $rulers['traditional'], 'modern' => $rulers['modern']],
                'occupants' => array_keys(array_filter($positions, static fn (array $position, string $key): bool => isset(self::PLANETS[$key]) && $position['house'] === $house, ARRAY_FILTER_USE_BOTH)),
            ];
        }
        $aspectFacts = [];
        $keys = array_keys($positions);
        foreach ($keys as $index => $first) {
            foreach (array_slice($keys, $index + 1) as $second) {
                if (! isset(self::PLANETS[$first]) && ! isset(self::PLANETS[$second])) {
                    continue;
                }
                foreach ($aspects->between($positions[$first]['longitude'], $positions[$second]['longitude'], self::ASPECT_ORB) as $aspect) {
                    $orbSeconds = (int) round($aspect['exact_orb'] * 3600);
                    $aspectFacts[] = [
                        'first' => $first, 'second' => $second, ...$aspect,
                        'orb_dms' => sprintf('%d° %02d′ %02d″', intdiv($orbSeconds, 3600), intdiv($orbSeconds % 3600, 60), $orbSeconds % 60),
                        'applying_or_separating' => null,
                    ];
                }
            }
        }
        $birth = $chart->birth_data_id === null ? null : $chart->birthData;

        return [
            ...$facts,
            'technical' => [
                'zodiac' => 'tropical', 'houses' => 'Placidus', 'reference' => 'geocentric',
                'rulership' => 'tradicional prioritaria; moderna complementaria',
                'dignities' => 'domicilio, detrimento, exaltación y caída clásicos; otras condiciones no calculadas',
                'aspect_types' => ['conjunction', 'sextile', 'square', 'trine', 'opposition'],
                'maximum_orb' => self::ASPECT_ORB,
                'aspect_source' => 'calculados a partir de longitudes del snapshot actual; mismo umbral que la matriz visual',
                'time_source' => $birth?->time_source ?? 'unknown',
                'time_precision' => $birth?->time_precision ?? 'unknown',
                'angles_houses_conditional' => ($birth?->time_precision ?? 'unknown') !== 'exact',
                'unknown_conditions' => ['aplicación/separación', 'velocidades', 'retrogradación', 'combustión', 'dignidades menores'],
                'sources' => ['Manual Maestro Universal incorporado en el prompt, edición revisada', 'Snapshot calculado de la carta actual; motor: '.($chart->engine_version ?? 'no identificado')],
                'manual_sha256' => self::MANUAL_SHA256,
            ],
            'positions' => $positions, 'houses' => $houseFacts, 'aspects' => $aspectFacts,
            'lunar_phase' => ['elongation_degrees' => fmod($positions['moon']['longitude'] - $positions['sun']['longitude'] + 360, 360), 'source' => 'longitudes calculadas, no calendario externo'],
        ];
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['concepts' => [
                'type' => 'array', 'minItems' => 1,
                'items' => [
                    'type' => 'object', 'properties' => [
                        'title' => ['type' => 'string'],
                        'characteristics' => [
                            'type' => 'array', 'minItems' => 1,
                            'items' => [
                                'type' => 'object', 'properties' => [
                                    'title' => ['type' => 'string'],
                                    'paragraphs' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
                                ],
                                'required' => ['title', 'paragraphs'], 'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['title', 'characteristics'], 'additionalProperties' => false,
                ],
            ]],
            'required' => ['concepts'], 'additionalProperties' => false,
        ];
    }

    public function auditSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['replacements' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object', 'properties' => [
                        'chapter' => ['type' => 'string', 'enum' => self::CHAPTERS],
                        'content' => $this->schema(),
                    ],
                    'required' => ['chapter', 'content'], 'additionalProperties' => false,
                ],
            ]],
            'required' => ['replacements'], 'additionalProperties' => false,
        ];
    }

    public function validate(array $content): void
    {
        if (array_keys($content) !== ['concepts']) {
            throw new RuntimeException('El bloque debe contener exclusivamente conceptos con características desarrolladas.');
        }
        $this->requireList($content['concepts']);
        $headings = [];
        $titleIssues = ['closing' => [], 'technical' => [], 'jargon' => [], 'advice' => [], 'inference' => []];
        $problems = [];
        $check = static function (callable $validation) use (&$problems): void {
            try {
                $validation();
            } catch (RuntimeException $exception) {
                $problems[$exception->getMessage()] = true;
            }
        };
        foreach ($content['concepts'] as $concept) {
            $this->requireObject($concept, ['title', 'characteristics']);
            $check(function () use ($concept, &$headings, &$titleIssues): void {
                $this->validateHeading($concept['title'], $headings, $titleIssues);
            });
            $this->requireList($concept['characteristics']);
            if (count($concept['characteristics']) > self::MAX_CHARACTERISTICS) {
                $problems['Cada concepto admite como máximo '.self::MAX_CHARACTERISTICS.' características diferenciadas; «'.$concept['title'].'» tiene '.count($concept['characteristics']).'.'] = true;
            }
            foreach ($concept['characteristics'] as $characteristic) {
                $this->requireObject($characteristic, ['title', 'paragraphs']);
                $check(function () use ($characteristic, &$headings, &$titleIssues): void {
                    $this->validateHeading($characteristic['title'], $headings, $titleIssues);
                });
                $this->requireList($characteristic['paragraphs']);
                foreach ($characteristic['paragraphs'] as $paragraph) {
                    $check(fn () => $this->validateText($paragraph));
                }
            }
        }
        if ($titleIssues['closing'] !== []) {
            $problems['El Informe I no admite introducciones, resúmenes ni síntesis o cierres. Renombra como rasgos vividos, sin esas palabras, estos títulos: «'.implode('», «', $titleIssues['closing']).'».'] = true;
        }
        if ($titleIssues['technical'] !== []) {
            $problems['Los títulos deben estar en lenguaje llano, sin grados, decimales, comillas ni paréntesis (lleva la casa, el signo o el aspecto al párrafo). Reescribe estos títulos: «'.implode('», «', $titleIssues['technical']).'».'] = true;
        }
        if ($titleIssues['jargon'] !== []) {
            $problems['Las características nombran vivencias, no pasos del análisis: casas numeradas, cúspides, regentes, dispositores, ocupantes, dignidades y aspectos fundamentan los párrafos, no los títulos. Reescribe como rasgos vividos estos títulos: «'.implode('», «', $titleIssues['jargon']).'».'] = true;
        }
        if ($titleIssues['advice'] !== []) {
            $problems['Las características interpretan significados; los ejercicios y planes de acción solo pueden aparecer brevemente dentro de un párrafo. Describir hábitos o rutinas no equivale a prescribirlos. Sustituye estas características por manifestaciones interpretativas: «'.implode('», «', $titleIssues['advice']).'».'] = true;
        }
        if ($titleIssues['inference'] !== []) {
            $problems['No atribuyas rasgos corporales por signo, carencias deducidas de la polaridad ni rasgos de otras personas: describe tu forma de vivir el factor. Reescribe estos títulos: «'.implode('», «', $titleIssues['inference']).'».'] = true;
        }
        if ($problems !== []) {
            throw new RuntimeException(implode(' ', array_keys($problems)));
        }
        if ($this->wordCount($content) > self::CHAPTER_MAX_WORDS) {
            throw new RuntimeException('Extensión de bloque inválida: supera el límite técnico de '.self::CHAPTER_MAX_WORDS.' palabras; siete páginas es un objetivo flexible, no una cuota.');
        }
    }

    public function validateFacts(array $content, array $facts, ?string $door = null): void
    {
        $text = implode(".\n", $this->texts($content));
        $signs = implode('|', array_map(static fn (string $sign): string => preg_quote($sign, '/'), self::SIGNS));
        $positions = $facts['positions'];
        $labels = array_column($positions, 'label');
        $byLabel = [];
        foreach ($positions as $position) {
            $byLabel[mb_strtolower($position['label'])] = $position;
        }
        $names = implode('|', array_map(static fn (string $label): string => preg_quote($label, '/'), $labels));
        $placementVerbs = '(?:(?:está|situad[oa]|ubicad[oa]|se encuentra)\s+)*';
        foreach ($this->assertedClaims('/\b('.$names.')\s+'.$placementVerbs.'en\s+('.$signs.')\b/ui', $text) as $claim) {
            $position = $byLabel[mb_strtolower($claim[1])];
            if (mb_strtolower($claim[2]) !== mb_strtolower($position['sign'])) {
                throw new RuntimeException('El texto contradice una posición canónica: '.$claim[0]);
            }
        }
        foreach ($positions as $position) {
            $pattern = '/\b'.preg_quote($position['label'], '/').'\b(?:\s*,(?:(?!\b(?:'.$names.')\b)[^.!?()]){0,70}?,)?\s+'.$placementVerbs.'(?:en\s+)?(?:'.$signs.'\s*,?\s*(?:en\s+)?)?casa\s+([IVX]+|\d{1,2})\b/ui';
            foreach ($this->assertedClaims($pattern, $text) as $claim) {
                $number = $this->houseNumber($claim[1]);
                if ($number !== $position['house']) {
                    throw new RuntimeException('El texto contradice la casa verificada: '.$claim[0]);
                }
            }
        }
        $aspectNames = 'conjunción|sextil|cuadratura|trígono|trino|oposición';
        $types = ['conjunción' => 'conjunction', 'sextil' => 'sextile', 'cuadratura' => 'square', 'trígono' => 'trine', 'trino' => 'trine', 'oposición' => 'opposition'];
        $patterns = [
            '/\b(?<type>'.$aspectNames.')\s+(?:entre\s+)?(?:el\s+|la\s+)?(?<first>'.$names.')\s*(?:[-–—]|y|con|a|al)\s*(?:el\s+|la\s+)?(?<second>'.$names.')\b/ui',
            '/\b(?<first>'.$names.')\s+(?:(?:forma|hace)\s+|(?:está\s+)?en\s+)?(?:un[ao]?\s+)?(?<type>'.$aspectNames.')\s+(?:con|a|al)\s+(?:el\s+|la\s+)?(?<second>'.$names.')\b/ui',
        ];
        $keysByLabel = [];
        foreach ($positions as $key => $position) {
            $keysByLabel[mb_strtolower($position['label'])] = $key;
        }
        foreach ($patterns as $pattern) {
            foreach ($this->assertedClaims($pattern, $text) as $claim) {
                $first = $keysByLabel[mb_strtolower($claim['first'])];
                $second = $keysByLabel[mb_strtolower($claim['second'])];
                $type = $types[mb_strtolower($claim['type'])];
                if ($type === 'opposition' && in_array($first, ['ascendant', 'descendant'], true)
                    && in_array($second, ['ascendant', 'descendant'], true) && $first !== $second) {
                    continue;
                }
                $matches = array_filter($facts['aspects'], static fn (array $aspect): bool => $aspect['key'] === $type
                    && (($aspect['first'] === $first && $aspect['second'] === $second) || ($aspect['first'] === $second && $aspect['second'] === $first)));
                if ($matches === []) {
                    throw new RuntimeException('El texto atribuye un aspecto no verificado: '.$claim[0]);
                }
            }
        }
    }

    // Only immediate non-assertive prefixes qualify; unrelated negations must not hide later claims.
    private function assertedClaims(string $pattern, string $text): array
    {
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $nonAssertion = '/\b(?:(?:no|nunca|tampoco|jamás)\s+(?:(?:se\s+)?(?:hay|existe|exige|requiere|implica|supone|significa|presupone|afirma|asume|indica|equivale\s+a|trata\s+de|es)\s+)?|(?:ni|sin)\s+|como\s+si\s+)(?:(?:un[ao]?|el|la|tu|su)\s+)?$/ui';
        $claims = [];
        foreach ($matches as $match) {
            $before = substr($text, 0, $match[0][1]);
            if (preg_match($nonAssertion, $before)) {
                continue;
            }
            $claims[] = array_map(static fn (array $group): string => $group[0], $match);
        }

        return $claims;
    }

    public function assertNoDuplicateParagraphs(array $chapters): void
    {
        $seen = [];
        foreach ($chapters as $content) {
            foreach ($this->texts($content) as $paragraph) {
                if ($this->wordCount([$paragraph]) < 12) {
                    continue;
                }
                $normalized = mb_strtolower(preg_replace('/[^\p{L}\p{N}]/u', '', $paragraph));
                if (isset($seen[$normalized])) {
                    throw new RuntimeException('El informe repite un párrafo. Revisa el bloque sin duplicar interpretaciones.');
                }
                $seen[$normalized] = true;
            }
        }
    }

    public function wordCount(array $content): int
    {
        return preg_match_all('/[\p{L}\p{N}]+(?:[’\'-][\p{L}\p{N}]+)*/u', implode(' ', $this->texts($content)));
    }

    private function texts(array $content): array
    {
        $texts = [];
        array_walk_recursive($content, static function ($text) use (&$texts): void {
            if (is_string($text)) {
                $texts[] = $text;
            }
        });

        return $texts;
    }

    private function requireList(mixed $value): void
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            throw new RuntimeException('Cada concepto requiere características y cada característica una lista no vacía de párrafos.');
        }
    }

    private function requireObject(mixed $value, array $keys): void
    {
        if (! is_array($value) || count($value) !== count($keys) || array_diff($keys, array_keys($value))) {
            throw new RuntimeException('La estructura debe conservar concepto, característica y párrafos sin secciones adicionales.');
        }
    }

    private function validateHeading(mixed $title, array &$seen, array &$issues): void
    {
        $this->validateText($title);
        if (preg_match('/\b(introducci[oó]n|pr[oó]logo|resumen|s[ií]ntesis|conclusi[oó]n|cierre|recapitulaci[oó]n)\b|^\s*presentaci[oó]n\b/ui', $title)) {
            $issues['closing'][] = $title;
        } elseif (preg_match('/[°′″"\'“”«»()\[\]]|\d+[.,]\d+/u', $title)) {
            $issues['technical'][] = $title;
        } elseif (preg_match('/\b(?:dignidad\w*|ca[ií]da|detrimento|exaltaci[oó]n|domicilio|dispositor\w*|dispositiv[oa]s?|cadente|angular|regentes?|regencias?|orbes?|c[uú]spides?|ocupantes?|cadena|sextil|tr[ií]gono|cuadratura|quincuncio)\b|\bcasas? (?:uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce|[IVX]+|\d+)\b/ui', $title)) {
            $issues['jargon'][] = $title;
        } elseif (preg_match('/^(?:ejercicios?|talleres?|cronogramas?)\b|^plan(?:es)? de acci[oó]n\b|^pr[aá]cticas? (?:para|de)\b|\bv[ií]as pr[aá]cticas\b|\bc[oó]mo corregir/ui', $title)) {
            $issues['advice'][] = $title;
        } elseif (preg_match('/\b(?:tu cuerpo|timbre de (?:tu |la )?voz|cualidades f[ií]sicas de (?:tu |la )?voz|voz (?:grave|aguda|ronca)|apariencia|constituci[oó]n|complexi[oó]n|rasgos f[ií]sicos|personas que|parejas? que|socios que)\b/ui', $title)) {
            $issues['inference'][] = $title;
        }
        $key = mb_strtolower(trim($title));
        if (isset($seen[$key])) {
            throw new RuntimeException('Los conceptos y características deben tener títulos diferenciados.');
        }
        $seen[$key] = true;
    }

    private function validateText(mixed $text): void
    {
        if (! is_string($text) || trim($text) === '' || preg_match('/<[^>]*>|^\s*(?:#{1,6}\s|[-*•]\s)/mu', $text)) {
            throw new RuntimeException('El contenido debe ser texto plano no vacío, sin listas de adjetivos ni formato incrustado.');
        }
        if (preg_match(self::INTERNAL_FIELD_NAMES, $text, $match)) {
            throw new RuntimeException("El texto no puede mostrar nombres internos de datos ({$match[0]}); explícalo en lenguaje natural.");
        }
        if (preg_match('/\d+[.,]\d+\s*°|\borb\b/u', $text)) {
            throw new RuntimeException('Expresa grados y orbes en grados y minutos o con palabras (por ejemplo, «a menos de un grado» o «5° 23′»), sin decimales ni la palabra inglesa «orb».');
        }
    }

    private function longitude(mixed $position, string $name, bool $checkSign = true): float
    {
        if (! is_array($position) || ! isset($position['longitude']) || ! is_numeric($position['longitude'])
            || ! is_finite((float) $position['longitude']) || $position['longitude'] < 0 || $position['longitude'] >= 360) {
            throw new RuntimeException("Falta {$name} o su longitud es inválida.");
        }
        $longitude = (float) $position['longitude'];
        if ($checkSign && ($position['sign'] ?? null) !== $this->signKey($longitude)) {
            throw new RuntimeException("Signo y longitud inconsistentes: {$name}.");
        }

        return $longitude;
    }

    private function signKey(float $longitude): string
    {
        return array_keys(self::SIGNS)[(int) floor($longitude / 30)];
    }

    private function house(float $longitude, array $houses): int
    {
        foreach (range(1, 12) as $house) {
            $start = (float) $houses[$house]['longitude'];
            $end = (float) $houses[$house === 12 ? 1 : $house + 1]['longitude'];
            if (fmod($longitude - $start + 360, 360) < fmod($end - $start + 360, 360)) {
                return $house;
            }
        }
        throw new RuntimeException('No se ha podido asignar la casa a partir de las cúspides verificadas.');
    }

    private function houseNumber(string $house): int
    {
        if (ctype_digit($house)) {
            return (int) $house;
        }
        $index = array_search(strtoupper($house), ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'], true);

        return $index === false ? 0 : $index + 1;
    }

    private function dignities(string $point, string $sign): array
    {
        $domiciles = [
            'sun' => ['leo'], 'moon' => ['cancer'], 'mercury' => ['gemini', 'virgo'],
            'venus' => ['taurus', 'libra'], 'mars' => ['aries', 'scorpio'],
            'jupiter' => ['sagittarius', 'pisces'], 'saturn' => ['capricorn', 'aquarius'],
        ];
        $exaltations = ['sun' => 'aries', 'moon' => 'taurus', 'mercury' => 'virgo', 'venus' => 'pisces', 'mars' => 'capricorn', 'jupiter' => 'cancer', 'saturn' => 'libra'];
        $signs = array_keys(self::SIGNS);
        $opposite = $signs[(array_search($sign, $signs, true) + 6) % 12];
        $conditions = [];
        if (in_array($sign, $domiciles[$point] ?? [], true)) {
            $conditions[] = 'domicilio';
        }
        if (in_array($opposite, $domiciles[$point] ?? [], true)) {
            $conditions[] = 'detrimento';
        }
        if (($exaltations[$point] ?? null) === $sign) {
            $conditions[] = 'exaltación';
        }
        if (($exaltations[$point] ?? null) === $opposite) {
            $conditions[] = 'caída';
        }

        return $conditions;
    }
}
