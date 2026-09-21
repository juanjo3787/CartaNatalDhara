<?php

namespace App\Domain\Astrology;

use InvalidArgumentException;

final class RegencyResolver
{
    private const TRADITIONAL = [
        'aries' => ['mars'],
        'taurus' => ['venus'],
        'gemini' => ['mercury'],
        'cancer' => ['moon'],
        'leo' => ['sun'],
        'virgo' => ['mercury'],
        'libra' => ['venus'],
        'scorpio' => ['mars'],
        'sagittarius' => ['jupiter'],
        'capricorn' => ['saturn'],
        'aquarius' => ['saturn'],
        'pisces' => ['jupiter'],
    ];

    private const MODERN = [
        'aries' => ['mars'],
        'taurus' => ['venus'],
        'gemini' => ['mercury'],
        'cancer' => ['moon'],
        'leo' => ['sun'],
        'virgo' => ['mercury'],
        'libra' => ['venus'],
        'scorpio' => ['mars', 'pluto'],
        'sagittarius' => ['jupiter'],
        'capricorn' => ['saturn'],
        'aquarius' => ['saturn', 'uranus'],
        'pisces' => ['jupiter', 'neptune'],
    ];

    public function resolve(string $sign): array
    {
        $normalizedSign = strtolower(trim($sign));

        if (!array_key_exists($normalizedSign, self::TRADITIONAL)) {
            throw new InvalidArgumentException("Unknown zodiac sign: {$sign}");
        }

        return [
            'traditional' => self::TRADITIONAL[$normalizedSign],
            'modern' => self::MODERN[$normalizedSign],
            'equal_weight' => true,
        ];
    }
}
