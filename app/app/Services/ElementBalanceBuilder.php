<?php

namespace App\Services;

class ElementBalanceBuilder
{
    /**
     * @param  list<array{key: string, label: string, longitude: float}>  $points  Normalized aspect-matrix points.
     * @return array{total: int, elements: list<array{label: string, planets: list<string>, count: int, percentage: float}>}
     */
    public function build(array $points): array
    {
        $planetKeys = ['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto'];
        $elements = array_map(
            static fn (string $label): array => ['label' => $label, 'planets' => [], 'count' => 0, 'percentage' => 0.0],
            ['Fuego', 'Tierra', 'Aire', 'Agua'],
        );
        $total = 0;
        foreach ($points as $point) {
            if (! in_array($point['key'], $planetKeys, true)) {
                continue;
            }

            $elementIndex = (int) floor($point['longitude'] / 30) % 4;
            $elements[$elementIndex]['planets'][] = $point['label'];
            $elements[$elementIndex]['count']++;
            $total++;
        }
        foreach ($elements as &$element) {
            $element['percentage'] = $total > 0 ? round($element['count'] / $total * 100, 1) : 0.0;
        }
        unset($element);

        return ['total' => $total, 'elements' => $elements];
    }
}
