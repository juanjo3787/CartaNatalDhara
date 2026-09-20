<?php

namespace App\Domain\Astrology;

use InvalidArgumentException;

/**
 * Normaliza datos brutos de nacimiento (entrada de formulario) en un
 * modelo BirthData persistible, resolviendo la zona horaria a UTC.
 */
final class BirthDataNormalizer
{
    public function __construct(
        private readonly TimezoneResolver $timezoneResolver,
    ) {
    }

    /**
     * @param array{
     *   local_date: string,
     *   local_time: string,
     *   timezone_identifier: string,
     *   time_source?: string,
     *   time_precision?: string,
     * } $input
     */
    public function normalize(array $input): NormalizedBirthData
    {
        if (empty($input['local_date']) || empty($input['local_time']) || empty($input['timezone_identifier'])) {
            throw new InvalidArgumentException('local_date, local_time y timezone_identifier son obligatorios.');
        }

        $resolved = $this->timezoneResolver->resolve(
            $input['local_date'],
            $input['local_time'],
            $input['timezone_identifier'],
        );

        return new NormalizedBirthData(
            localDate: $input['local_date'],
            localTime: $input['local_time'],
            timezoneIdentifier: $input['timezone_identifier'],
            utcDatetime: "{$resolved->utcDate} {$resolved->utcTime}",
            utcOffset: $resolved->utcOffset,
            timeSource: $input['time_source'] ?? 'unknown',
            timePrecision: $input['time_precision'] ?? 'unknown',
        );
    }
}
