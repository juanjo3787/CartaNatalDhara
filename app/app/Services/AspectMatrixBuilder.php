<?php

namespace App\Services;

final class AspectMatrixBuilder
{
    private const POINT_DEFINITIONS = [
        ['key' => 'sun', 'source' => 'sun', 'label' => 'Sol', 'glyph' => '☉'],
        ['key' => 'moon', 'source' => 'moon', 'label' => 'Luna', 'glyph' => '☽'],
        ['key' => 'mercury', 'source' => 'mercury', 'label' => 'Mercurio', 'glyph' => '☿'],
        ['key' => 'venus', 'source' => 'venus', 'label' => 'Venus', 'glyph' => '♀'],
        ['key' => 'mars', 'source' => 'mars', 'label' => 'Marte', 'glyph' => '♂'],
        ['key' => 'jupiter', 'source' => 'jupiter', 'label' => 'Júpiter', 'glyph' => '♃'],
        ['key' => 'saturn', 'source' => 'saturn', 'label' => 'Saturno', 'glyph' => '♄'],
        ['key' => 'uranus', 'source' => 'uranus', 'label' => 'Urano', 'glyph' => '♅'],
        ['key' => 'neptune', 'source' => 'neptune', 'label' => 'Neptuno', 'glyph' => '♆'],
        ['key' => 'pluto', 'source' => 'pluto', 'label' => 'Plutón', 'glyph' => '♇'],
        ['key' => 'rahu', 'source' => 'true_node', 'label' => 'Nodo norte', 'glyph' => '☊'],
        ['key' => 'ketu', 'source' => 'true_node', 'label' => 'Nodo sur', 'glyph' => '☋', 'opposite' => true],
        ['key' => 'lilith', 'source' => 'mean_apogee', 'label' => 'Lilith', 'glyph' => '⚸'],
        ['key' => 'ascendant', 'source' => 'ascendant', 'label' => 'Ascendente', 'glyph' => 'AC'],
        ['key' => 'midheaven', 'source' => 'midheaven', 'label' => 'Medio Cielo', 'glyph' => 'MC'],
    ];

    private const ASPECT_DEFINITIONS = [
        ['key' => 'conjunction', 'angle' => 0, 'label' => 'Conjunción', 'glyph' => '☌'],
        ['key' => 'opposition', 'angle' => 180, 'label' => 'Oposición', 'glyph' => '☍'],
        ['key' => 'trine', 'angle' => 120, 'label' => 'Trígono', 'glyph' => '△'],
        ['key' => 'square', 'angle' => 90, 'label' => 'Cuadratura', 'glyph' => '□'],
        ['key' => 'sextile', 'angle' => 60, 'label' => 'Sextil', 'glyph' => '✶'],
    ];

    /**
     * @return array{
     *     points: list<array{key: string, label: string, glyph: string, longitude: float, position: string}>,
     *     matches: array<string, list<array{key: string, label: string, glyph: string, orb: float}>>
     * }
     */
    public function build(array $snapshot, float $maximumOrb = 6.0): array
    {
        $points = [];
        foreach (self::POINT_DEFINITIONS as $definition) {
            $position = $snapshot[$definition['source']] ?? null;
            if (! is_array($position) || ! isset($position['longitude'])) {
                continue;
            }

            $longitude = $this->normalize((float) $position['longitude']);
            if ($definition['opposite'] ?? false) {
                $longitude = $this->normalize($longitude + 180);
            }

            $points[] = [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'glyph' => $definition['glyph'],
                'longitude' => $longitude,
                'position' => $this->formatLongitude($longitude),
            ];
        }

        $matches = [];
        for ($firstIndex = 0; $firstIndex < count($points); $firstIndex++) {
            for ($secondIndex = $firstIndex + 1; $secondIndex < count($points); $secondIndex++) {
                $firstPoint = $points[$firstIndex];
                $secondPoint = $points[$secondIndex];
                $difference = $this->normalize($firstPoint['longitude'] - $secondPoint['longitude']);
                $separation = min($difference, 360 - $difference);
                $pairMatches = [];

                foreach (self::ASPECT_DEFINITIONS as $aspect) {
                    $orb = abs($separation - $aspect['angle']);
                    if ($orb <= $maximumOrb) {
                        $pairMatches[] = [
                            'key' => $aspect['key'],
                            'label' => $aspect['label'],
                            'glyph' => $aspect['glyph'],
                            'orb' => round($orb, 1),
                        ];
                    }
                }

                $matches[$firstPoint['key'].'|'.$secondPoint['key']] = $pairMatches;
            }
        }

        return ['points' => $points, 'matches' => $matches];
    }

    private function formatLongitude(float $longitude): string
    {
        $signs = ['Aries', 'Tauro', 'Géminis', 'Cáncer', 'Leo', 'Virgo', 'Libra', 'Escorpio', 'Sagitario', 'Capricornio', 'Acuario', 'Piscis'];
        $signIndex = min(11, (int) floor($longitude / 30));
        $withinSign = $longitude - ($signIndex * 30);
        $degrees = (int) floor($withinSign);
        $minutesWithFraction = ($withinSign - $degrees) * 60;
        $minutes = (int) floor($minutesWithFraction);
        $seconds = round(($minutesWithFraction - $minutes) * 60, 1);

        return sprintf('%s %d° %d\' %s"', $signs[$signIndex], $degrees, $minutes, number_format($seconds, 1, '.', ''));
    }

    private function normalize(float $longitude): float
    {
        $normalized = fmod($longitude, 360);

        return $normalized < 0 ? $normalized + 360 : $normalized;
    }
}
