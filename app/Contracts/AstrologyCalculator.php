<?php

namespace App\Contracts;

use App\Domain\Astrology\BirthData;
use App\Domain\Astrology\ChartSnapshot;

interface AstrologyCalculator
{
    public function calculate(BirthData $birthData): ChartSnapshot;
}
