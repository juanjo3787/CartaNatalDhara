<?php

namespace App\Domain\Astrology;

final readonly class ResolvedTimezone
{
    public function __construct(
        public string $utcDate,
        public string $utcTime,
        public string $utcOffset,
    ) {
    }
}
