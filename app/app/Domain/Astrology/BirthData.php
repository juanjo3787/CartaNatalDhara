<?php

namespace App\Domain\Astrology;

/**
 * Datos de entrada para AstrologyCalculator. localDate/localTime deben venir
 * ya en UTC (swetest -ut espera Tiempo Universal, no hora local de nacimiento).
 * Usar TimezoneResolver/BirthDataNormalizer para convertir la hora local antes
 * de construir este objeto.
 */
final readonly class BirthData
{
    public function __construct(
        public string $localDate,
        public string $localTime,
        public string $timezone,
        public float $latitude,
        public float $longitude,
    ) {
    }
}
