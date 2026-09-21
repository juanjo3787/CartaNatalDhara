<?php

namespace App\Console\Commands;

use App\Domain\Astrology\BirthDataNormalizer;
use App\Models\BirthData;
use App\Models\Person;
use App\Models\Place;
use App\Services\ChartService;
use Illuminate\Console\Command;

/**
 * Comando de prueba end-to-end: crea una persona, un lugar y una carta
 * natal real usando Swiss Ephemeris, para validar todo el flujo de datos.
 */
class CalculateDemoChart extends Command
{
    protected $signature = 'chart:demo
        {alias=Demo : Alias de la persona}
        {date=1990-06-15 : Fecha local de nacimiento (YYYY-MM-DD)}
        {time=14:30 : Hora local de nacimiento (HH:MM)}
        {timezone=Europe/Madrid : Identificador de zona horaria IANA}
        {lat=40.4 : Latitud del lugar}
        {lon=-3.7 : Longitud del lugar}';

    protected $description = 'Calcula una carta natal de prueba de principio a fin y la persiste en la base de datos.';

    public function handle(BirthDataNormalizer $normalizer, ChartService $chartService): int
    {
        $place = Place::create([
            'city' => 'Demo',
            'country' => 'Demo',
            'latitude' => $this->argument('lat'),
            'longitude' => $this->argument('lon'),
            'timezone_identifier' => $this->argument('timezone'),
        ]);

        $person = Person::create(['alias' => $this->argument('alias')]);

        $normalized = $normalizer->normalize([
            'local_date' => $this->argument('date'),
            'local_time' => $this->argument('time'),
            'timezone_identifier' => $this->argument('timezone'),
        ]);

        $birthData = BirthData::create([
            'person_id' => $person->id,
            'place_id' => $place->id,
            'local_date' => $normalized->localDate,
            'local_time' => $normalized->localTime,
            'timezone_identifier' => $normalized->timezoneIdentifier,
            'utc_offset' => $normalized->utcOffset,
            'utc_datetime' => $normalized->utcDatetime,
            'time_source' => $normalized->timeSource,
            'time_precision' => $normalized->timePrecision,
        ]);

        $chart = $chartService->calculateFor($birthData->load('place'));

        $this->info("Carta #{$chart->id} calculada.");
        $this->line('UTC usado: '.$normalized->utcDatetime.' (offset '.$normalized->utcOffset.')');
        $this->line('Sol: '.json_encode($chart->snapshot['sun'] ?? null));
        $this->line('Luna: '.json_encode($chart->snapshot['moon'] ?? null));
        $this->line('Ascendente: '.json_encode($chart->snapshot['ascendant'] ?? null));

        return self::SUCCESS;
    }
}
