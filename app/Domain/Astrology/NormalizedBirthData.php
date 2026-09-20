<?php

namespace App\Domain\Astrology;

final readonly class NormalizedBirthData
{
    public function __construct(
        public string $localDate,
        public string $localTime,
        public string $timezoneIdentifier,
        public string $utcDatetime,
        public string $utcOffset,
        public string $timeSource,
        public string $timePrecision,
    ) {
    }
}
