<?php

namespace App\Domain\Astrology;

final class AstrologicalProtocol
{
    public const ZODIAC = 'tropical';
    public const REFERENCE = 'geocentric';
    public const HOUSES = 'placidus';
    public const LUNAR_NODE = 'true';
    public const LILITH = 'mean';

    public static function toArray(): array
    {
        return [
            'zodiac' => self::ZODIAC,
            'reference' => self::REFERENCE,
            'houses' => self::HOUSES,
            'lunar_node' => self::LUNAR_NODE,
            'lilith' => self::LILITH,
        ];
    }
}
