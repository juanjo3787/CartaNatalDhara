<?php

namespace App\Services;

final class NatalWheelRenderer
{
    public function render(array $snapshot): string
    {
        $cx = 220;
        $cy = 220;
        $outerRadius = 195;
        $innerRadius = 148;
        $houseRadius = 125;
        $signRadius = 172;
        $ascendantLongitude = (float) ($snapshot['ascendant']['longitude'] ?? 90);
        $rotationDeg = 90 - $ascendantLongitude;

        $signs = [
            ['name' => 'Aries', 'glyph' => '♈', 'color' => '#d946ef'],
            ['name' => 'Tauro', 'glyph' => '♉', 'color' => '#34d399'],
            ['name' => 'Géminis', 'glyph' => '♊', 'color' => '#60a5fa'],
            ['name' => 'Cáncer', 'glyph' => '♋', 'color' => '#22c55e'],
            ['name' => 'Leo', 'glyph' => '♌', 'color' => '#f59e0b'],
            ['name' => 'Virgo', 'glyph' => '♍', 'color' => '#a78bfa'],
            ['name' => 'Libra', 'glyph' => '♎', 'color' => '#f472b6'],
            ['name' => 'Escorpio', 'glyph' => '♏', 'color' => '#ef4444'],
            ['name' => 'Sagitario', 'glyph' => '♐', 'color' => '#f59e0b'],
            ['name' => 'Capricornio', 'glyph' => '♑', 'color' => '#38bdf8'],
            ['name' => 'Acuario', 'glyph' => '♒', 'color' => '#60a5fa'],
            ['name' => 'Piscis', 'glyph' => '♓', 'color' => '#14b8a6'],
        ];

        $planetGlyphs = [
            'sun' => '☉',
            'moon' => '☽',
            'mercury' => '☿',
            'venus' => '♀',
            'mars' => '♂',
            'jupiter' => '♃',
            'saturn' => '♄',
            'uranus' => '♅',
            'neptune' => '♆',
            'pluto' => '♇',
            'true_node' => '☊',
            'mean_apogee' => '☍',
            'ascendant' => 'Asc',
            'midheaven' => 'MC',
        ];

        $planetColors = [
            'sun' => '#f59e0b',
            'moon' => '#a78bfa',
            'mercury' => '#60a5fa',
            'venus' => '#f472b6',
            'mars' => '#ef4444',
            'jupiter' => '#22c55e',
            'saturn' => '#38bdf8',
            'uranus' => '#14b8a6',
            'neptune' => '#a855f7',
            'pluto' => '#9ca3af',
            'true_node' => '#facc15',
            'mean_apogee' => '#f97316',
        ];

        $points = [
            'sun' => $snapshot['sun'] ?? null,
            'moon' => $snapshot['moon'] ?? null,
            'mercury' => $snapshot['mercury'] ?? null,
            'venus' => $snapshot['venus'] ?? null,
            'mars' => $snapshot['mars'] ?? null,
            'jupiter' => $snapshot['jupiter'] ?? null,
            'saturn' => $snapshot['saturn'] ?? null,
            'uranus' => $snapshot['uranus'] ?? null,
            'neptune' => $snapshot['neptune'] ?? null,
            'pluto' => $snapshot['pluto'] ?? null,
            'true_node' => $snapshot['true_node'] ?? null,
            'mean_apogee' => $snapshot['mean_apogee'] ?? null,
            'ascendant' => $snapshot['ascendant'] ?? null,
            'midheaven' => $snapshot['midheaven'] ?? null,
        ];

        $lines = [];
        $signsSvg = [];
        $houseLabels = [];
        $pointsSvg = [];
        $aspectLines = [];
        $houseCusps = $snapshot['houses'] ?? [];

        foreach (range(0, 11) as $index) {
            $baseAngle = deg2rad(90 - ($index * 30) - $rotationDeg);
            $innerX = $cx + cos($baseAngle) * $innerRadius;
            $innerY = $cy - sin($baseAngle) * $innerRadius;
            $outerX = $cx + cos($baseAngle) * $outerRadius;
            $outerY = $cy - sin($baseAngle) * $outerRadius;

            $lines[] = sprintf(
                '<line class="sign-boundary-%d" x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#9ca3af" stroke-width="0.8" opacity="0.8" />',
                $index + 1,
                $innerX,
                $innerY,
                $outerX,
                $outerY,
            );

            $houseLongitude = $houseCusps[$index + 1]['longitude'] ?? (($index * 30) + 15);
            $cuspAngle = deg2rad(90 - $houseLongitude - $rotationDeg);
            $cuspX = $cx + cos($cuspAngle) * $outerRadius;
            $cuspY = $cy - sin($cuspAngle) * $outerRadius;
            $lines[] = sprintf(
                '<line class="house-cusp-%d" x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#4b5563" stroke-width="0.8" opacity="0.7" />',
                $index + 1,
                $cx,
                $cy,
                $cuspX,
                $cuspY,
            );
            $pointsSvg[] = sprintf(
                '<circle class="house-cusp-point-%d" cx="%.2f" cy="%.2f" r="2.8" fill="#374151" stroke="#ffffff" stroke-width="0.5" />',
                $index + 1,
                $cuspX,
                $cuspY,
            );

            $signAngle = deg2rad(90 - (($index * 30) + 15) - $rotationDeg);
            $signX = $cx + cos($signAngle) * $signRadius;
            $signY = $cy - sin($signAngle) * $signRadius;
            $sign = $signs[$index];

            $signsSvg[] = sprintf(
                '<text x="%.2f" y="%.2f" font-size="15" text-anchor="middle" fill="%s" font-weight="700">%s</text>',
                $signX,
                $signY,
                $sign['color'],
                $sign['glyph'],
            );
            $signsSvg[] = sprintf(
                '<text x="%.2f" y="%.2f" font-size="8" text-anchor="middle" fill="#4b5563">%s</text>',
                $signX,
                $signY + 15,
                $sign['name'],
            );

            $currentCusp = (float) ($houseCusps[$index + 1]['longitude'] ?? ($index * 30));
            $nextCusp = (float) ($houseCusps[$index === 11 ? 1 : $index + 2]['longitude'] ?? (($index + 1) * 30));
            $houseSpan = fmod($nextCusp - $currentCusp + 360, 360);
            $houseMidpoint = fmod($currentCusp + ($houseSpan / 2) + 360, 360);
            $houseAngle = deg2rad(90 - $houseMidpoint - $rotationDeg);
            $houseX = $cx + cos($houseAngle) * $houseRadius;
            $houseY = $cy - sin($houseAngle) * $houseRadius;
            $houseLabels[] = sprintf(
                '<text x="%.2f" y="%.2f" font-size="9" text-anchor="middle" fill="#3b3b3b">%s</text>',
                $houseX,
                $houseY,
                $index + 1,
            );
        }

        $planetSvg = [];
        foreach ($points as $key => $point) {
            if (! $point || ! isset($point['longitude'])) {
                continue;
            }

            $glyph = $planetGlyphs[$key] ?? null;
            if ($glyph === null) {
                continue;
            }

            $angle = deg2rad(90 - $point['longitude'] - $rotationDeg);
            $planetX = $cx + cos($angle) * $outerRadius;
            $planetY = $cy - sin($angle) * $outerRadius;
            $color = $planetColors[$key] ?? '#374151';

            $planetSvg[] = sprintf(
                '<text x="%.2f" y="%.2f" font-size="18" text-anchor="middle" fill="%s" font-weight="700">%s</text>',
                $planetX,
                $planetY,
                $color,
                $glyph,
            );

            $degreeText = sprintf('%d°', (int) floor($point['degrees']));
            $labelX = $cx + cos($angle) * ($outerRadius + 12);
            $labelY = $cy - sin($angle) * ($outerRadius + 12);
            $planetSvg[] = sprintf(
                '<text x="%.2f" y="%.2f" font-size="8" text-anchor="middle" fill="#374151">%s</text>',
                $labelX,
                $labelY,
                $degreeText,
            );

            if (in_array($key, ['sun', 'moon', 'ascendant', 'midheaven'], true)) {
                $pointsSvg[] = sprintf(
                    '<circle class="point-%s" cx="%.2f" cy="%.2f" r="5" fill="%s" stroke="#ffffff" stroke-width="1.6" />',
                    $key,
                    $planetX,
                    $planetY,
                    $color,
                );
            }

            if ($key === 'ascendant' || $key === 'midheaven') {
                $pointsSvg[] = sprintf(
                    '<text x="%.2f" y="%.2f" font-size="8" text-anchor="middle" fill="#374151" font-weight="700">%s</text>',
                    $planetX,
                    $planetY - 10,
                    $glyph,
                );
            }
        }

        $pointCoordinates = [];
        foreach ($points as $key => $point) {
            if (! $point || ! isset($point['longitude'])) {
                continue;
            }

            $angle = deg2rad(90 - $point['longitude'] - $rotationDeg);
            $pointCoordinates[$key] = [
                'x' => $cx + cos($angle) * ($outerRadius - 20),
                'y' => $cy - sin($angle) * ($outerRadius - 20),
            ];
        }

        $aspectPairs = [
            ['sun', 'moon', '#ef4444'],
            ['sun', 'ascendant', '#22c55e'],
            ['moon', 'midheaven', '#3b82f6'],
        ];

        foreach ($aspectPairs as [$a, $b, $color]) {
            if (! isset($pointCoordinates[$a], $pointCoordinates[$b])) {
                continue;
            }

            $aspectLines[] = sprintf(
                '<line class="aspect-line" x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="%s" stroke-width="1.6" opacity="0.85" />',
                $pointCoordinates[$a]['x'],
                $pointCoordinates[$a]['y'],
                $pointCoordinates[$b]['x'],
                $pointCoordinates[$b]['y'],
                $color,
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="440" height="440" viewBox="0 0 440 440" role="img" aria-label="Rueda natal con casas">'
            . '<rect x="0" y="0" width="440" height="440" fill="#ffffff"/>'
            . '<circle cx="%d" cy="%d" r="%d" fill="#ffffff" stroke="#1f2937" stroke-width="2.2"/>'
            . '<circle cx="%d" cy="%d" r="%d" fill="none" stroke="#d1d5db" stroke-width="1.2"/>'
            . '<circle cx="%d" cy="%d" r="%d" fill="none" stroke="#d1d5db" stroke-width="1.1"/>'
            . '%s'
            . '%s'
            . '%s'
            . '%s'
            . '%s'
            . '%s'
            . '</svg>',
            $cx,
            $cy,
            $outerRadius,
            $cx,
            $cy,
            $innerRadius,
            $cx,
            $cy,
            $houseRadius,
            implode('', $lines),
            implode('', $aspectLines),
            implode('', $signsSvg),
            implode('', $houseLabels),
            implode('', $planetSvg),
            implode('', $pointsSvg),
        );
    }
}
