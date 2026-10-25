<?php

namespace Tests\Fixtures;

use App\Models\BirthData;
use App\Models\Chart;
use App\Models\Person;
use App\Models\Place;
use App\Services\InteriorMapMaster;
use App\Services\PhaseOneReportService;

trait InteriorMapFixture
{
    public static function snapshot(): array
    {
        $snapshot = ['houses' => []];
        $signs = array_keys(InteriorMapMaster::SIGNS);
        foreach (range(1, 12) as $house) {
            $snapshot['houses'][$house] = ['longitude' => ($house - 1) * 30, 'sign' => $signs[$house - 1], 'degrees' => 0, 'minutes' => 0, 'seconds' => 0];
        }
        foreach ([
            'sun' => 95, 'moon' => 245, 'mercury' => 65, 'venus' => 215, 'mars' => 275,
            'jupiter' => 345, 'saturn' => 305, 'uranus' => 245, 'neptune' => 275, 'pluto' => 215,
            'ascendant' => 0, 'descendant' => 180, 'midheaven' => 270,
        ] as $point => $longitude) {
            $snapshot[$point] = ['longitude' => $longitude, 'sign' => $signs[(int) floor($longitude / 30)], 'degrees' => $longitude % 30, 'minutes' => 0, 'seconds' => 0];
        }

        return $snapshot;
    }

    public static function chapter(string $chapter): array
    {
        return ['concepts' => array_map(static fn (int $concept): array => [
            'title' => 'Concepto '.$chapter.' '.$concept,
            'keywords' => ['Criterio', 'Cooperación', 'Autonomía', 'Equilibrio', 'Decisión'],
            'characteristics' => array_map(static fn (int $characteristic): array => [
                'title' => 'Característica '.$chapter.' '.$concept.' '.$characteristic,
                'paragraphs' => [
                    $chapter.' '.$concept.' '.$characteristic.' '.implode(' ', array_map(static fn (int $word): string => 'observación'.$chapter.$concept.$characteristic.'x'.$word, range(1, 110))),
                ],
            ], [1, 2]),
        ], [1, 2]), 'states' => array_map(static fn (string $label): array => [
            'paragraphs' => ['En '.$chapter.', '.$label.' permite contrastar una posibilidad simbólica específica sin atribuir hechos biográficos.'],
            'keywords' => ['Criterio', 'Cooperación', 'Autonomía', 'Equilibrio', 'Decisión'],
        ], InteriorMapMaster::STATES)];
    }

    private function chart(): Chart
    {
        $person = Person::create(['alias' => 'Synthetic reader', 'full_name' => 'Persona sintética Ñ']);
        $place = Place::create(['city' => 'Madrid', 'country' => 'ES', 'latitude' => 40.4, 'longitude' => -3.7, 'timezone_identifier' => 'Europe/Madrid']);
        $birth = BirthData::create(['person_id' => $person->id, 'place_id' => $place->id, 'local_date' => '2000-01-01', 'local_time' => '12:00:00', 'timezone_identifier' => 'Europe/Madrid', 'utc_offset' => '+01:00', 'utc_datetime' => '2000-01-01 11:00:00', 'time_source' => 'unknown', 'time_precision' => 'unknown']);

        return Chart::create([
            'person_id' => $person->id, 'birth_data_id' => $birth->id,
            'configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus', 'reference' => 'geocentric'],
            'snapshot' => self::snapshot(), 'engine_version' => 'synthetic', 'status' => 'calculated',
            'natal_wheel_image' => 'data:image/jpeg;base64,'.base64_encode(file_get_contents(__DIR__.'/wheel.jpg')),
        ]);
    }

    private function saveChapters(Chart $chart): void
    {
        foreach (InteriorMapMaster::CHAPTERS as $chapter) {
            app(PhaseOneReportService::class)->storeChapter($chart, $chapter === 'opposites' ? null : $chapter, self::chapter($chapter));
        }
    }
}
