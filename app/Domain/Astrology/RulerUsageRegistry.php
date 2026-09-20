<?php

namespace App\Domain\Astrology;

use App\Models\Chart;

/**
 * Tracks which planets have already been introduced as rulers in previous doors of a chart,
 * so a later door's "ruler" block can adapt its wording instead of repeating the explanation.
 */
final class RulerUsageRegistry
{
    /**
     * @return array<string, string> planet key => door that first introduced it, for doors before $door.
     */
    public function alreadyIntroduced(Chart $chart, string $door): array
    {
        $priorDoors = DoorSequence::doorsBefore($door);

        if ($priorDoors === []) {
            return [];
        }

        $rows = $chart->interpretations()
            ->whereIn('door', $priorDoors)
            ->where('block', 'ruler')
            ->orderByRaw("field(door, '" . implode("','", DoorSequence::ORDER) . "')")
            ->get(['door', 'rulers_used']);

        $introduced = [];

        foreach ($rows as $row) {
            foreach ((array) $row->rulers_used as $planet) {
                if (! isset($introduced[$planet])) {
                    $introduced[$planet] = $row->door;
                }
            }
        }

        return $introduced;
    }

    /**
     * @param list<string> $rulers planet keys ruling the current door's sign.
     * @return list<string> the subset of $rulers not yet introduced in a previous door.
     */
    public function pendingIntroduction(Chart $chart, string $door, array $rulers): array
    {
        $introduced = $this->alreadyIntroduced($chart, $door);

        return array_values(array_filter($rulers, fn (string $planet) => ! isset($introduced[$planet])));
    }
}
