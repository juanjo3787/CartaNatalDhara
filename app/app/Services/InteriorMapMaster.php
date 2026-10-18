<?php

namespace App\Services;

use App\Models\Chart;
use RuntimeException;

final class InteriorMapMaster
{
    public const VERSION = 100;

    public const DOORS = ['sol', 'luna', 'ascendente', 'descendente'];

    public const SIGNS = [
        'aries' => 'Aries', 'taurus' => 'Tauro', 'gemini' => 'Géminis',
        'cancer' => 'Cáncer', 'leo' => 'Leo', 'virgo' => 'Virgo',
        'libra' => 'Libra', 'scorpio' => 'Escorpio', 'sagittarius' => 'Sagitario',
        'capricorn' => 'Capricornio', 'aquarius' => 'Acuario', 'pisces' => 'Piscis',
    ];

    public const TITLES = [
        'function' => 'Qué representa y qué se trabaja desde esta puerta',
        'entry_question' => 'Pregunta de entrada',
        'sign' => 'Las características del signo',
        'house' => 'Qué aporta la casa',
        'harmony' => 'Expresión armónica',
        'deficit' => 'Expresión desarmónica por defecto',
        'excess' => 'Expresión desarmónica por exceso',
        'distinction_question' => 'Pregunta para distinguirlas',
        'opposite' => 'Tu opuesto complementario',
        'example' => 'Un ejemplo cotidiano',
        'summary' => 'Tu mapa de esta puerta',
        'essential_question' => 'Pregunta esencial',
        'practice' => 'Práctica',
    ];

    public const INTEGRATION_TITLES = [
        'purpose' => 'Qué se trabaja en este apartado',
        'themes' => 'Puntos de integración y preguntas',
        'practice' => 'Tu práctica de integración',
        'essential_question' => 'Pregunta esencial',
    ];

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
Eres la redactora del Informe I — Mapa interior de ASTROLOGÍA SARANA VEDA.
Este es el único prompt maestro activo. Sigue una estructura breve y desarrollada, no el dossier histórico.
Escribe en español claro, cercano y respetuoso, para quien no sabe astrología. Usa segunda persona sin deducir género del nombre.
La lectura es simbólica, no una prueba científica, diagnóstico ni predicción. Las posibilidades pueden reconocerse, matizarse o descartarse.
Utiliza exclusivamente los datos verificados y ASTROLOGICAL_FACTS de esta solicitud: tropical, geocéntrico y Placidus.
No calcules, completes, corrijas ni inventes posiciones, grados, signos o casas. Si faltan datos obligatorios, la aplicación debe detener la generación.
Cada informe comienza desde cero. No utilices informes históricos, otras personas, nombres ni posiciones de una plantilla de ejemplo.
No deduzcas profesión, familia, salud, orientación sexual ni hechos biográficos. Los ejemplos son hipotéticos.
Sol: identidad, voluntad, elección y expresión. Luna: necesidades emocionales, seguridad y cuidado.
Ascendente: orientación inicial, respuesta ante lo nuevo y comienzo. Descendente: encuentro, reciprocidad, acuerdos y aprendizaje vincular.
En cada puerta sigue: significado, pregunta de entrada, signo aplicado a su función, casa, armonía, defecto, exceso,
pregunta para distinguirlos, opuesto complementario y clave de integración, ejemplo cotidiano, síntesis, pregunta esencial y práctica observable.
Armonía: cualidad disponible con flexibilidad y medida. Defecto: dificultad para acceder a esa cualidad. Exceso: domina y pierde flexibilidad.
No son juicios morales ni etiquetas personales. No atribuyas la misma conducta a defecto y exceso sin explicar el mecanismo distinto.
El opuesto complementario aporta recursos, no sustituye al signo ni supone planetas en él. Parejas: Aries–Libra, Tauro–Escorpio,
Géminis–Sagitario, Cáncer–Capricornio, Leo–Acuario, Virgo–Piscis. Distingue las funciones del eje Ascendente–Descendente.
La integración conjunta contiene de tres a cinco temas específicos de las cuatro posiciones actuales, con puntos de trabajo,
preguntas y práctica que distinga qué quiero, qué siento, cómo comienzo y qué necesito acordar.
No copies temas del ejemplo Word ni resumas simplemente las cuatro puertas.
Explica conceptos generales solo una vez. Usa párrafos breves y listas cuando ayuden; no repitas la ficha característica/pauta/ejemplo por punto.
El informe completo tiene entre 4000 y 5500 palabras, incluida su introducción. No rellenes ni repitas para alcanzar extensión.
No añadas capítulos de regentes, aspectos planetarios, otros planetas, doce casas, ampliaciones comerciales ni cuotas del modelo antiguo.
La aplicación aplica tipografía, tamaños, colores, beige, títulos, encabezados, pies y A4 originales de Sarana Veda; no generes CSS ni paginación.
Devuelve solo el JSON estructurado solicitado. Cada string es texto plano, sin HTML, Markdown ni numeración de secciones.
Antes de entregar comprueba datos canónicos, las cuatro funciones, estados distintos, opuestos correctos, ejemplos hipotéticos,
ausencia de datos de otras personas, repeticiones, contradicciones y contenido del modelo antiguo.
PROMPT;
    }

    public function fingerprint(Chart $chart): string
    {
        $chart->load('person', 'birthData.place');

        return hash('sha256', json_encode([
            'configuration' => $chart->configuration,
            'snapshot' => $chart->snapshot,
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
        $snapshot = $chart->snapshot;
        foreach (range(1, 12) as $house) {
            if (! isset($snapshot['houses'][$house]['longitude']) || ! is_numeric($snapshot['houses'][$house]['longitude'])) {
                throw new RuntimeException("Falta la cúspide verificada de la casa {$house}.");
            }
            $longitude = (float) $snapshot['houses'][$house]['longitude'];
            if (! is_finite($longitude) || $longitude < 0 || $longitude >= 360) {
                throw new RuntimeException("Cúspide inválida en la casa {$house}.");
            }
        }
        $spans = [];
        foreach (range(1, 12) as $house) {
            $spans[] = fmod((float) $snapshot['houses'][$house === 12 ? 1 : $house + 1]['longitude'] - (float) $snapshot['houses'][$house]['longitude'] + 360, 360);
        }
        if (min($spans) <= 0 || abs(array_sum($spans) - 360) > 0.01) {
            throw new RuntimeException('Las cúspides no forman una secuencia completa y ordenada.');
        }
        $facts = [];
        $signs = array_keys(self::SIGNS);
        foreach (array_combine(self::DOORS, ['sun', 'moon', 'ascendant', 'descendant']) as $door => $point) {
            $position = $snapshot[$point] ?? [];
            if (! isset($position['sign'], $position['longitude']) || ! isset(self::SIGNS[$position['sign']])
                || ! is_numeric($position['longitude']) || ! is_finite((float) $position['longitude'])) {
                throw new RuntimeException("Falta una posición válida y calculada: {$point}.");
            }
            $longitude = (float) $position['longitude'];
            if ($longitude < 0 || $longitude >= 360 || $signs[(int) floor($longitude / 30)] !== $position['sign']) {
                throw new RuntimeException("Signo y longitud inconsistentes: {$point}.");
            }
            $house = $door === 'ascendente' ? 1 : ($door === 'descendente' ? 7 : $this->house($longitude, $snapshot['houses']));
            $oppositeIndex = (array_search($position['sign'], $signs, true) + 6) % 12;
            $facts[$door] = [
                'sign' => self::SIGNS[$position['sign']],
                'house' => $house,
                'longitude' => $longitude,
                'opposite' => self::SIGNS[$signs[$oppositeIndex]],
            ];
        }
        if (abs(fmod($facts['descendente']['longitude'] - $facts['ascendente']['longitude'] + 360, 360) - 180) > 0.01) {
            throw new RuntimeException('Ascendente y Descendente no forman un eje verificado.');
        }
        foreach (['ascendente' => 1, 'descendente' => 7] as $door => $house) {
            if (abs($facts[$door]['longitude'] - (float) $snapshot['houses'][$house]['longitude']) > 0.01) {
                throw new RuntimeException('El eje y las cúspides I/VII no coinciden.');
            }
        }

        return $facts;
    }

    public function schema(bool $integration = false): array
    {
        $properties = [];
        foreach (array_keys($integration ? self::INTEGRATION_TITLES : self::TITLES) as $key) {
            $properties[$key] = ['type' => 'array', 'items' => ['type' => 'string']];
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }

    public function validate(array $content, bool $integration = false): void
    {
        $expected = array_keys($integration ? self::INTEGRATION_TITLES : self::TITLES);
        if (array_diff($expected, array_keys($content)) || array_diff(array_keys($content), $expected)) {
            throw new RuntimeException('El capítulo no contiene exactamente las secciones del nuevo modelo.');
        }
        foreach ($content as $paragraphs) {
            if (! is_array($paragraphs) || ! array_is_list($paragraphs) || $paragraphs === []) {
                throw new RuntimeException('Cada sección debe contener una lista no vacía de párrafos.');
            }
            if ($integration && (count($content['themes']) < 3 || count($content['themes']) > 5)) {
                throw new RuntimeException('La integración requiere de tres a cinco temas.');
            }
            foreach ($paragraphs as $text) {
                if (! is_string($text) || trim($text) === '' || preg_match('/<[^>]*>|^\s*#{1,6}\s/mu', $text)) {
                    throw new RuntimeException('El contenido del nuevo modelo debe ser texto plano no vacío.');
                }
            }
        }
        $words = $this->wordCount($content);
        $minimum = $integration ? 800 : 780;
        $maximum = $integration ? 1050 : 1050;
        if ($words < $minimum || $words > $maximum) {
            throw new RuntimeException("Extensión de capítulo inválida: {$words} palabras; se requieren {$minimum}–{$maximum}.");
        }
    }

    public function wordCount(array $content): int
    {
        return preg_match_all('/[\p{L}\p{N}]+(?:[’\'-][\p{L}\p{N}]+)*/u', implode(' ', array_merge(...array_values($content))));
    }

    public function validateFacts(array $content, array $facts, ?string $door): void
    {
        $text = implode(' ', array_merge(...array_values($content)));
        $signs = implode('|', array_map(static fn ($sign) => preg_quote($sign, '/'), array_values(self::SIGNS)));
        preg_match_all('/\b(Sol|Luna|Ascendente|Descendente)\s+(?:(?:está|situad[oa]|se encuentra)\s+)?en\s+('.$signs.')\b/ui', $text, $claims, PREG_SET_ORDER);
        foreach ($claims as $claim) {
            $key = mb_strtolower($claim[1]);
            if (mb_strtolower($claim[2]) !== mb_strtolower($facts[$key]['sign'])) {
                throw new RuntimeException('El texto contradice una posición canónica: '.$claim[0]);
            }
        }
        if ($door !== null) {
            $opposite = implode(' ', $content['opposite']);
            if (! preg_match('/\b'.preg_quote($facts[$door]['opposite'], '/').'\b/ui', $opposite)) {
                throw new RuntimeException('El apartado complementario no identifica el opuesto calculado.');
            }
            preg_match_all('/\bcasa\s+([IVX]+|\d{1,2})\b/ui', implode(' ', $content['house']), $houses);
            $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
            foreach ($houses[1] as $house) {
                $romanIndex = array_search(strtoupper($house), $roman, true);
                $number = ctype_digit($house) ? (int) $house : ($romanIndex === false ? 0 : $romanIndex + 1);
                if ($number !== $facts[$door]['house']) {
                    throw new RuntimeException('El texto contradice la casa calculada.');
                }
            }
        }
    }

    public function assertNoDuplicateParagraphs(array $chapters): void
    {
        $seen = [];
        foreach ($chapters as $content) {
            foreach (array_merge(...array_values($content)) as $paragraph) {
                $words = preg_split('/\s+/u', trim($paragraph));
                if (count($words) < 12) {
                    continue;
                }
                $normalized = mb_strtolower(preg_replace('/[^\p{L}\p{N}]/u', '', $paragraph));
                if (isset($seen[$normalized])) {
                    throw new RuntimeException('El informe repite un párrafo. Regenera el capítulo antes de validar.');
                }
                $seen[$normalized] = true;
            }
        }
    }

    private function house(float $longitude, array $houses): int
    {
        foreach (range(1, 12) as $house) {
            $start = (float) $houses[$house]['longitude'];
            $end = (float) $houses[$house === 12 ? 1 : $house + 1]['longitude'];
            $span = fmod($end - $start + 360, 360);
            $offset = fmod($longitude - $start + 360, 360);
            if ($span > 0 && $offset < $span) {
                return $house;
            }
        }
        throw new RuntimeException('No se ha podido asignar la casa a partir de las cúspides verificadas.');
    }
}
