<?php

namespace Tests\Unit;

use App\Services\AspectMatrixBuilder;
use PHPUnit\Framework\TestCase;

class AspectMatrixBuilderTest extends TestCase
{
    public function test_it_builds_position_rows_and_aspects_from_snapshot_longitudes(): void
    {
        $snapshot = [
            'sun' => ['longitude' => 359],
            'moon' => ['longitude' => 1],
            'mercury' => ['longitude' => 178],
            'true_node' => ['longitude' => 20],
            'mean_apogee' => ['longitude' => 90],
            'ascendant' => ['longitude' => 45],
            'midheaven' => ['longitude' => 270],
        ];

        $matrix = (new AspectMatrixBuilder)->build($snapshot);
        $pointLongitudes = array_column($matrix['points'], 'longitude', 'key');
        $sunMoonAspects = $matrix['matches']['sun|moon'];

        $this->assertEqualsWithDelta(200, $pointLongitudes['ketu'], 0.0001);
        $this->assertSame('Piscis 29° 0\' 0.0"', $matrix['points'][0]['position']);
        $this->assertSame('conjunction', $sunMoonAspects[0]['key']);
        $this->assertEqualsWithDelta(2, $sunMoonAspects[0]['orb'], 0.0001);
        $this->assertSame('opposition', $matrix['matches']['sun|mercury'][0]['key']);
    }
}
