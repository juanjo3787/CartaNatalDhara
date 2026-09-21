<?php

namespace App\Services;

use App\Contracts\AstrologyCalculator;
use App\Domain\Astrology\BirthData as CalculatorBirthData;
use App\Models\BirthData;
use App\Models\Chart;

/**
 * Orquesta el calculo de una carta natal a partir de un registro BirthData
 * ya persistido (con su Place asociado) y guarda el resultado como snapshot
 * inmutable en la tabla charts.
 */
final class ChartService
{
    public function __construct(
        private readonly AstrologyCalculator $calculator,
    ) {
    }

    public function calculateFor(BirthData $birthData): Chart
    {
        $place = $birthData->place;

        [$utcDate, $utcTime] = explode(' ', $birthData->utc_datetime->format('Y-m-d H:i:s'));

        $calculatorInput = new CalculatorBirthData(
            localDate: $utcDate,
            localTime: $utcTime,
            timezone: 'UTC',
            latitude: (float) $place->latitude,
            longitude: (float) $place->longitude,
        );

        $snapshot = $this->calculator->calculate($calculatorInput);

        return Chart::create([
            'person_id' => $birthData->person_id,
            'birth_data_id' => $birthData->id,
            'configuration' => $snapshot->configuration,
            'engine_version' => 'swetest',
            'snapshot' => $snapshot->positions,
            'status' => 'calculated',
        ]);
    }
}
