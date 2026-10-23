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
                $matches[$firstPoint['key'].'|'.$secondPoint['key']] = array_map(
                    static fn (array $aspect): array => array_intersect_key($aspect, array_flip(['key', 'label', 'glyph', 'orb'])),
                    $this->between($firstPoint['longitude'], $secondPoint['longitude'], $maximumOrb),
                );
            }
        }

        return ['points' => $points, 'matches' => $matches];
    }

    public function between(float $first, float $second, float $maximumOrb = 6.0): array
    {
        $difference = $this->normalize($first - $second);
        $separation = min($difference, 360 - $difference);
        $matches = [];
        foreach (self::ASPECT_DEFINITIONS as $aspect) {
            $orb = abs($separation - $aspect['angle']);
            if ($orb <= $maximumOrb) {
                $matches[] = [
                    ...$aspect,
                    'orb' => round($orb, 1),
                    'exact_orb' => $orb,
                    'separation' => $separation,
                ];
            }
        }

        return $matches;
    }

    public function formatLongitude(float $longitude): string
    {
        $signs = ['Aries', 'Tauro', 'Géminis', 'Cáncer', 'Leo', 'Virgo', 'Libra', 'Escorpio', 'Sagitario', 'Capricornio', 'Acuario', 'Piscis'];
        $tenths = (int) round($this->normalize($longitude) * 36000) % 12960000;
        $signIndex = intdiv($tenths, 1080000);
        $withinSign = $tenths % 1080000;
        $degrees = intdiv($withinSign, 36000);
        $minutes = intdiv($withinSign % 36000, 600);
        $seconds = ($withinSign % 600) / 10;

        return sprintf('%s %d° %d\' %s"', $signs[$signIndex], $degrees, $minutes, number_format($seconds, 1, '.', ''));
    }

    private function normalize(float $longitude): float
    {
        $normalized = fmod($longitude, 360);

        return $normalized < 0 ? $normalized + 360 : $normalized;
    }
}
