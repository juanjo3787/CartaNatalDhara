<?php

namespace App\Domain\Astrology;

/**
 * Canonical door order and block taxonomy used to classify templates and interpretations.
 */
final class DoorSequence
{
    public const SOL = 'sol';
    public const LUNA = 'luna';
    public const ASCENDENTE = 'ascendente';
    public const DESCENDENTE = 'descendente';

    public const ORDER = [self::SOL, self::LUNA, self::ASCENDENTE, self::DESCENDENTE];

    public const SHARED_BLOCKS = ['shared_intro', 'shared_states', 'shared_conclusions'];

    public const DOOR_BLOCKS = [
        'function', 'sign', 'house', 'ruler', 'integration', 'harmony', 'deficit', 'excess', 'closing',
    ];

    /**
     * @return list<string> doors that are generated before the given door, in order.
     */
    public static function doorsBefore(string $door): array
    {
        $position = array_search($door, self::ORDER, true);

        if ($position === false) {
            throw new \InvalidArgumentException("Unknown door: {$door}");
        }

        return array_slice(self::ORDER, 0, $position);
    }
}
