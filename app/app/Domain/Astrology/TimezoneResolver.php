<?php

namespace App\Domain\Astrology;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Resuelve la conversion de fecha/hora local de nacimiento a UTC usando la
 * base de datos de zonas horarias del sistema (tzdata), que ya incluye las
 * reglas historicas de horario de verano.
 */
final class TimezoneResolver
{
    public function resolve(string $localDate, string $localTime, string $timezoneIdentifier): ResolvedTimezone
    {
        $localDateTime = new DateTimeImmutable(
            "{$localDate} {$localTime}",
            new DateTimeZone($timezoneIdentifier),
        );

        $utcDateTime = $localDateTime->setTimezone(new DateTimeZone('UTC'));

        return new ResolvedTimezone(
            utcDate: $utcDateTime->format('Y-m-d'),
            utcTime: $utcDateTime->format('H:i:s'),
            utcOffset: $localDateTime->format('P'),
        );
    }
}
