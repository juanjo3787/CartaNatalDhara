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
            $text = $chapter.' '.$key.' '.implode(' ', array_map(static fn ($i) => 'observación'.$index.'x'.$i, range(1, $chapter === 'integration' ? 73 : 46)));
            $content[$key] = [$text];
        }
        if ($chapter === 'integration') {
            $content['themes'] = array_map(static fn (string $theme): array => [
                'title' => 'Tema '.$theme,
                'paragraphs' => [$theme.' '.str_repeat('Elección consciente y cuidado disponible. ', 30)],
                'work_points' => ['Reconocer lo que necesitas.', 'Expresar una preferencia propia.', 'Acordar un siguiente paso.'],
                'question' => '¿Qué quieres cuidar en esta situación?',
            ], ['primero', 'segundo', 'tercero']);
        } else {
            $opposite = ['sol' => 'Capricornio', 'luna' => 'Géminis', 'ascendente' => 'Libra', 'descendente' => 'Aries'][$chapter];
            $content['opposite'][0] .= ' '.$opposite;
            $content['summary'] = array_map(static fn (string $label): string => $label.': '.$chapter.' '.str_repeat('Una cualidad que puedes observar. ', 3), ['DON', 'NECESIDAD', 'SOMBRA', 'RECURSO']);
            foreach (InteriorMapMaster::LIST_BLOCKS as $block) {
                if (isset($content[$block])) {
                    $content[$block] = array_map(static fn (array $words): string => implode(' ', $words), array_chunk(explode(' ', $content[$block][0]), 10));
                }
            }
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

    private function referenceChapters(): array
    {
        $path = base_path('../docs/plantilla_Mapa_Interior_Pastora_Maria_Villarreal_Muniz.docx');
        if (! is_file($path)) {
            $this->markTestSkipped('The retained editorial reference is not available.');
        }
        $archive = new \PharData($path, 0, null, \Phar::ZIP);
        $document = new \DOMDocument;
        $document->loadXML($archive['word/document.xml']->getContent());
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $chapters = [];
        $chapter = null;
        $block = null;
        $theme = -1;
        $themeField = null;
        foreach ($xpath->query('//w:body/w:p') as $paragraph) {
            $text = '';
            foreach ($xpath->query('.//w:t', $paragraph) as $run) {
                $text .= $run->textContent;
            }
            $style = $xpath->evaluate('string(w:pPr/w:pStyle/@w:val)', $paragraph);
            if ($style === 'Heading1' && preg_match('/^([1-5])\./u', $text, $match)) {
                $chapter = [...InteriorMapMaster::DOORS, 'integration'][(int) $match[1] - 1];
                $chapters[$chapter] = [];
                $block = null;

                continue;
            }
            if ($chapter === null || trim($text) === '') {
                continue;
            }
            if ($chapter === 'integration') {
                if (str_starts_with($style, 'Heading')) {
                    if ($text === 'Qué se trabaja en este apartado') {
                        $block = 'purpose';
                    } elseif ($text === 'Tu práctica de integración') {
                        $block = 'practice';
                    } elseif ($text === 'Pregunta esencial') {
                        $block = 'essential_question';
                    } elseif ($text === 'Puntos de trabajo') {
                        if ($theme < 0) {
                            $block = 'work_points';
                        } else {
                            $themeField = 'work_points';
                        }
                    } elseif ($text === 'Pregunta') {
                        $themeField = 'question';
                    } else {
                        $block = 'themes';
                        $theme++;
                        $chapters[$chapter]['themes'][$theme] = ['title' => $text, 'paragraphs' => [], 'work_points' => [], 'question' => ''];
                        $themeField = 'paragraphs';
                    }

                    continue;
                }
                if ($block === 'themes') {
                    if ($themeField === 'question') {
                        $chapters[$chapter]['themes'][$theme]['question'] = $text;
                    } else {
                        $chapters[$chapter]['themes'][$theme][$themeField][] = $text;
                    }
                } elseif ($block !== null) {
                    $chapters[$chapter][$block][] = $text;
                }

                continue;
            }
            if (str_starts_with($style, 'Heading')) {
                $block = match (true) {
                    str_starts_with($text, 'Qué representa el '), str_starts_with($text, 'Qué representa la Luna') => 'function',
                    $text === 'Pregunta de entrada' => 'entry_question',
                    str_starts_with($text, 'Las características de ') => 'sign_traits',
                    str_starts_with($text, 'Qué añade la casa'), str_starts_with($text, 'Qué representa la casa') => 'house_topics',
                    $text === 'Expresión armónica' => 'harmony',
                    $text === 'Expresión desarmónica por defecto' => 'deficit',
                    $text === 'Expresión desarmónica por exceso' => 'excess',
                    $text === 'Pregunta para distinguirlas' => 'distinction_question',
                    str_starts_with($text, 'Tu opuesto complementario'), $text === 'Qué es y para qué sirve' => 'opposite',
                    str_starts_with($text, 'Las características que aporta') => 'opposite_traits',
                    $text === 'Clave de integración' => 'integration_key',
                    $text === 'Un ejemplo cotidiano' => 'example',
                    str_starts_with($text, 'Tu mapa ') => 'summary',
                    $text === 'Pregunta esencial' => 'essential_question',
                    $text === 'Práctica' => 'practice',
                    default => $block,
                };

                continue;
            }
            if ($block === null || str_ends_with($text, ':')) {
                continue;
            }
            if ($style !== 'ListBullet') {
                $block = match ($block) {
                    'sign_traits' => 'sign', 'house_topics' => 'house',
                    'opposite_traits' => 'opposite_application', default => $block,
                };
            }
            $chapters[$chapter][$block][] = $text;
        }

        return $chapters;
    }
}
