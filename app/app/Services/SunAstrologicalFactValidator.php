<?php

namespace App\Services;

use RuntimeException;

final class SunAstrologicalFactValidator
{
    private const NAMES = [
        'sol' => 'sun', 'luna' => 'moon', 'mercurio' => 'mercury', 'venus' => 'venus',
        'marte' => 'mars', 'júpiter' => 'jupiter', 'saturno' => 'saturn',
        'urano' => 'uranus', 'neptuno' => 'neptune', 'plutón' => 'pluto',
        'ascendente' => 'ascendant', 'descendente' => 'descendant',
    ];

    private const SIGNS = 'Aries|Tauro|Géminis|Cáncer|Leo|Virgo|Libra|Escorpio|Sagitario|Capricornio|Acuario|Piscis';

    public function validate(array $result, array $facts): void
    {
        $texts = [];
        array_walk_recursive($result, static function (mixed $value) use (&$texts): void {
            if (is_string($value)) {
                $texts[] = $value;
            }
        });

        foreach ($texts as $text) {
            $name = implode('|', array_map(static fn (string $word): string => preg_quote($word, '/'), array_keys(self::NAMES)));
            $degreePattern = '/\b(?<subject>'.$name.')\s+(?:a|en)\s+(?<degree>\d{1,2})\s*[°º]/iu';
            if (preg_match_all($degreePattern, $text, $degreeMatches, PREG_SET_ORDER)) {
                foreach ($degreeMatches as $degreeMatch) {
                    $key = self::NAMES[mb_strtolower($degreeMatch['subject'])];
                    if ((int) $degreeMatch['degree'] !== (int) $facts[$key]['degrees']) {
                        throw new RuntimeException("Posición astrológica contradictoria: grado de {$degreeMatch['subject']}.");
                    }
                }
            }
            $housePattern = '/\b(?<subject>'.$name.')\s*,?\s*(?:(?:está|se\s+encuentra)\s+)?en\s+(?:la\s+)?casa\s+(?<house>XII|XI|X|IX|VIII|VII|VI|V|IV|III|II|I|1[0-2]|[1-9])\b/iu';
            if (preg_match_all($housePattern, $text, $houseMatches, PREG_SET_ORDER)) {
                foreach ($houseMatches as $houseMatch) {
                    $key = self::NAMES[mb_strtolower($houseMatch['subject'])];
                    if ($this->houseNumber($houseMatch['house']) !== (int) $facts[$key]['house']) {
                        throw new RuntimeException("Casa astrológica contradictoria para {$houseMatch['subject']}.");
                    }
                }
            }
            $pattern = '/\b(?<subjects>(?:'.$name.')(?:\s+y\s+(?:'.$name.'))*)\s*,?\s*(?:(?:está|se\s+encuentra)\s+)?en\s+(?:(?:el\s+)?signo\s+de\s+)?(?<sign>'.self::SIGNS.')\b/iu';
            if (! preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches as $match) {
                $subjects = preg_split('/\s+y\s+/iu', mb_strtolower($match['subjects'][0]));
                $remainder = substr($text, $match[0][1] + strlen($match[0][0]), 80);
                $tail = preg_split('/[.!?;\n]|\b(?:y\s+)?(?:'.$name.')\b/iu', $remainder)[0] ?? '';
                foreach ($subjects as $subject) {
                    $key = self::NAMES[$subject] ?? null;
                    if ($key === null || ! isset($facts[$key])) {
                        continue;
                    }
                    if (mb_strtolower($match['sign'][0]) !== mb_strtolower($facts[$key]['sign'])) {
                        throw new RuntimeException("Posición astrológica contradictoria: {$subject} en {$match['sign'][0]}.");
                    }
                    if (preg_match('/\b(?:en\s+)?casa\s+(?<house>XII|XI|X|IX|VIII|VII|VI|V|IV|III|II|I|1[0-2]|[1-9])\b/iu', $tail, $houseMatch)) {
                        $house = $this->houseNumber($houseMatch['house']);
                        if ($house !== (int) $facts[$key]['house']) {
                            throw new RuntimeException("Casa astrológica contradictoria para {$subject}: {$houseMatch['house']}.");
                        }
                    }
                }
            }
        }
    }

    private function houseNumber(string $value): int
    {
        $roman = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6, 'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10, 'XI' => 11, 'XII' => 12];
        return $roman[strtoupper($value)] ?? (int) $value;
    }
}
