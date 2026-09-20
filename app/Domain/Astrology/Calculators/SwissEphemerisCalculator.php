<?php

namespace App\Domain\Astrology\Calculators;

use App\Contracts\AstrologyCalculator;
use App\Domain\Astrology\BirthData;
use App\Domain\Astrology\ChartSnapshot;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Calculador basado en el binario swetest (Swiss Ephemeris), compilado en el
 * Dockerfile a partir de https://github.com/aloistr/swisseph (licencia AGPL).
 *
 * Formato de salida verificado ejecutando el binario real dentro del
 * contenedor Docker con: swetest -p0123456789tA -fPl -g, -head -house...
 * Cada linea tiene el formato "Nombre, longitud_decimal".
 */
final class SwissEphemerisCalculator implements AstrologyCalculator
{
    /** Letras de swetest para -p: 0-9 = Sol..Pluton, t = nodo verdadero, A = apogeo medio (Lilith). */
    private const PLANET_SEQUENCE = '0123456789tA';

    private const PLANET_NAMES = [
        'Sun' => 'sun',
        'Moon' => 'moon',
        'Mercury' => 'mercury',
        'Venus' => 'venus',
        'Mars' => 'mars',
        'Jupiter' => 'jupiter',
        'Saturn' => 'saturn',
        'Uranus' => 'uranus',
        'Neptune' => 'neptune',
        'Pluto' => 'pluto',
        'true Node' => 'true_node',
        'mean Apogee' => 'mean_apogee',
    ];

    private const ZODIAC_SIGNS = [
        'aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo',
        'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces',
    ];

    public function __construct(
        private readonly string $binaryPath,
        private readonly string $ephemerisPath,
    ) {
    }

    public function calculate(BirthData $birthData): ChartSnapshot
    {
        $command = $this->buildCommand($birthData);
        $process = PHP_OS_FAMILY === 'Windows'
            ? Process::fromShellCommandline($command)
            : new Process(['sh', '-c', $command]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('swetest failed: '.$process->getErrorOutput());
        }

        $rows = $this->parseOutput($process->getOutput());

        return new ChartSnapshot(
            calculatedAt: now()->toIso8601String(),
            positions: $this->buildPositions($rows),
            configuration: [
                'zodiac' => 'tropical',
                'houses' => 'placidus',
                'engine' => 'swetest',
            ],
        );
    }

    private function buildCommand(BirthData $birthData): string
    {
        [$year, $month, $day] = explode('-', $birthData->localDate);
        [$hour, $minute] = explode(':', $birthData->localTime);

        $dateArg = sprintf('%d.%d.%d', (int) $day, (int) $month, (int) $year);
        $timeArg = sprintf('%d:%d', (int) $hour, (int) $minute);
        $houseArg = sprintf('%s,%s,P', $birthData->longitude, $birthData->latitude);

        return sprintf(
            '%s -edir%s -b%s -ut%s -house%s -p%s -fPl -g, -head',
            $this->binaryPath,
            $this->ephemerisPath,
            $dateArg,
            $timeArg,
            $houseArg,
            self::PLANET_SEQUENCE,
        );
    }

    /**
     * @return array<string, float> nombre swetest => longitud decimal
     */
    private function parseOutput(string $output): array
    {
        $rows = [];

        foreach (explode("\n", trim($output)) as $line) {
            if (!str_contains($line, ',')) {
                continue;
            }

            [$name, $longitude] = explode(',', $line, 2);

            $rows[trim($name)] = (float) trim($longitude);
        }

        return $rows;
    }

    /**
     * @param array<string, float> $rows
     */
    private function buildPositions(array $rows): array
    {
        $positions = [];

        foreach (self::PLANET_NAMES as $sweName => $key) {
            if (!array_key_exists($sweName, $rows)) {
                continue;
            }

            $positions[$key] = $this->describeLongitude($rows[$sweName]);
        }

        $positions['ascendant'] = $this->describeLongitude($rows['Ascendant'] ?? 0.0);
        $positions['midheaven'] = $this->describeLongitude($rows['MC'] ?? 0.0);
        $positions['descendant'] = $this->describeLongitude($this->normalize(($rows['Ascendant'] ?? 0.0) + 180));

        $positions['houses'] = [];
        for ($house = 1; $house <= 12; $house++) {
            $key = sprintf('house  %d', $house);
            $key = array_key_exists($key, $rows) ? $key : sprintf('house %d', $house);

            if (array_key_exists($key, $rows)) {
                $positions['houses'][$house] = $this->describeLongitude($rows[$key]);
            }
        }

        return $positions;
    }

    private function describeLongitude(float $longitude): array
    {
        $longitude = $this->normalize($longitude);
        $signIndex = (int) floor($longitude / 30);
        $degreesInSign = $longitude - ($signIndex * 30);

        $degrees = (int) floor($degreesInSign);
        $minutesFloat = ($degreesInSign - $degrees) * 60;
        $minutes = (int) floor($minutesFloat);
        $seconds = ($minutesFloat - $minutes) * 60;

        return [
            'longitude' => $longitude,
            'sign' => self::ZODIAC_SIGNS[$signIndex],
            'degrees' => $degrees,
            'minutes' => $minutes,
            'seconds' => round($seconds, 2),
        ];
    }

    private function normalize(float $longitude): float
    {
        $longitude = fmod($longitude, 360);

        return $longitude < 0 ? $longitude + 360 : $longitude;
    }
}
