<?php

namespace App\Domain\Astrology;

final readonly class ChartSnapshot
{
    public function __construct(
        public string $calculatedAt,
        public array $positions,
        public array $configuration,
    ) {
    }
}
