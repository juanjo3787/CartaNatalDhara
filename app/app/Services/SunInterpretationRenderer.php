<?php

namespace App\Services;

final class SunInterpretationRenderer
{
    public function render(string $name, array $snapshot): array
    {
        $sun = $snapshot['sun'] ?? ['sign' => 'aries', 'degrees' => 0, 'minutes' => 0, 'seconds' => 0];
        $ascendant = $snapshot['ascendant'] ?? ['sign' => 'aries', 'degrees' => 0, 'minutes' => 0, 'seconds' => 0];
        $houses = $snapshot['houses'] ?? [];

        $catalog = new SunTemplateCatalog();

        $shared = $catalog->sharedBlocks();
        $blocks = $catalog->doorBlocks();

        $thisSign = $this->capitalize($sun['sign']);
        $house = $this->resolveHouse($sun, $houses);
        $ascSign = $this->capitalize($ascendant['sign']);

        return [
            'shared_intro' => sprintf(
                '%s. %s',
                $name,
                $shared['shared_intro'],
            ),
            'function' => sprintf(
                '%s. %s',
                $name,
                $blocks['function'],
            ),
            'sign' => sprintf(
                'El Sol en %s a %d° %d\' %d\" refleja la forma en que %s expresa su identidad y su dignidad personal.',
                $thisSign,
                (int) ($sun['degrees'] ?? 0),
                (int) ($sun['minutes'] ?? 0),
                (int) round($sun['seconds'] ?? 0),
                $name,
            ),
            'house' => sprintf(
                'El Sol se encuentra en la casa %d, cuyo signo de cúspide es %s. Con el Ascendente en %s, esta posición sitúa la autenticidad de %s en el ámbito de la vida donde puede hacerse visible y sostener un lugar claro.',
                $house['number'],
                $house['sign'],
                $ascSign,
                $name,
            ),
            'ruler' => $blocks['ruler'],
            'integration' => $blocks['integration'],
            'harmony' => $blocks['harmony'],
            'deficit' => $blocks['deficit'],
            'excess' => $blocks['excess'],
            'closing' => sprintf(
                '%s. %s',
                $name,
                $blocks['closing'],
            ),
        ];
    }

    private function resolveHouse(array $point, array $houses): array
    {
        $fallback = [
            'number' => 1,
            'sign' => 'Aries',
        ];

        if (! isset($point['longitude'])) {
            foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $houseNumber) {
                if (isset($houses[$houseNumber]['sign'])) {
                    return [
                        'number' => $houseNumber,
                        'sign' => $this->capitalize($houses[$houseNumber]['sign']),
                    ];
                }
            }

            return $fallback;
        }

        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $houseNumber) {
            $current = $houses[$houseNumber]['longitude'] ?? null;
            $nextNumber = $houseNumber === 12 ? 1 : $houseNumber + 1;
            $next = $houses[$nextNumber]['longitude'] ?? null;

            if ($current === null || $next === null) {
                continue;
            }

            $span = fmod($next - $current + 360, 360);
            $distance = fmod($point['longitude'] - $current + 360, 360);

            if ($distance <= $span) {
                return [
                    'number' => $houseNumber,
                    'sign' => $this->capitalize($houses[$houseNumber]['sign'] ?? 'Aries'),
                ];
            }
        }

        return $fallback;
    }

    private function capitalize(string $value): string
    {
        $map = [
            'aries' => 'Aries',
            'taurus' => 'Tauro',
            'gemini' => 'Géminis',
            'cancer' => 'Cáncer',
            'leo' => 'Leo',
            'virgo' => 'Virgo',
            'libra' => 'Libra',
            'scorpio' => 'Escorpio',
            'sagittarius' => 'Sagitario',
            'capricorn' => 'Capricornio',
            'aquarius' => 'Acuario',
            'pisces' => 'Piscis',
        ];

        $normalized = strtolower(trim($value));

        return $map[$normalized] ?? ucfirst($normalized);
    }
}
