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
        foreach (['sun' => 95, 'moon' => 245, 'ascendant' => 0, 'descendant' => 180, 'midheaven' => 270] as $point => $longitude) {
            $snapshot[$point] = ['longitude' => $longitude, 'sign' => $signs[(int) floor($longitude / 30)], 'degrees' => $longitude % 30, 'minutes' => 0, 'seconds' => 0];
        }

        return $snapshot;
    }

    public static function chapter(string $chapter): array
    {
        $titles = $chapter === 'integration' ? InteriorMapMaster::INTEGRATION_TITLES : InteriorMapMaster::TITLES;
        $content = [];
        foreach (array_keys($titles) as $index => $key) {
            $text = $chapter.' '.$key.' '.implode(' ', array_map(static fn ($i) => 'observación'.$index.'x'.$i, range(1, $chapter === 'integration' ? 73 : 64)));
            $content[$key] = [$text];
        }
        if ($chapter === 'integration') {
            $content['themes'] = [
                'Tema primero. '.str_repeat('Elección consciente y cuidado disponible. ', 38),
                'Tema segundo. '.str_repeat('Comenzar despacio y escuchar necesidades. ', 38),
                'Tema tercero. '.str_repeat('Acordar los límites con claridad. ', 38),
            ];
        } else {
            $opposite = ['sol' => 'Capricornio', 'luna' => 'Géminis', 'ascendente' => 'Libra', 'descendente' => 'Aries'][$chapter];
            $content['opposite'][0] .= ' '.$opposite;
        }

        return $content;
    }

    private function chart(): Chart
    {
        $person = Person::create(['alias' => 'Synthetic reader', 'full_name' => 'Persona sintética Ñ']);
        $place = Place::create(['city' => 'Madrid', 'country' => 'ES', 'latitude' => 40.4, 'longitude' => -3.7, 'timezone_identifier' => 'Europe/Madrid']);
        $birth = BirthData::create(['person_id' => $person->id, 'place_id' => $place->id, 'local_date' => '2000-01-01', 'local_time' => '12:00:00', 'timezone_identifier' => 'Europe/Madrid', 'utc_offset' => '+01:00', 'utc_datetime' => '2000-01-01 11:00:00']);

        return Chart::create([
            'person_id' => $person->id, 'birth_data_id' => $birth->id,
            'configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus', 'reference' => 'geocentric'],
            'snapshot' => self::snapshot(), 'engine_version' => 'synthetic', 'status' => 'calculated',
            'natal_wheel_image' => 'data:image/jpeg;base64,'.base64_encode(file_get_contents(__DIR__.'/wheel.jpg')),
        ]);
    }

    private function saveChapters(Chart $chart): void
    {
        foreach ([...InteriorMapMaster::DOORS, 'integration'] as $chapter) {
            app(PhaseOneReportService::class)->storeChapter($chart, $chapter === 'integration' ? null : $chapter, self::chapter($chapter));
        }
    }
}
