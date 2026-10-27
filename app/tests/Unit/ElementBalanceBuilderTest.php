<?php

namespace Tests\Unit;

use App\Services\AspectMatrixBuilder;
use App\Services\ElementBalanceBuilder;
use PHPUnit\Framework\TestCase;

class ElementBalanceBuilderTest extends TestCase
{
    public function test_ten_planets_have_equal_weight_and_other_points_are_excluded(): void
    {
        $snapshot = [];
        foreach ([
            'sun' => 0, 'moon' => 120, 'mercury' => 240,
            'venus' => 30, 'mars' => 150, 'jupiter' => 270,
            'saturn' => 60, 'uranus' => 180, 'neptune' => 300, 'pluto' => 330,
            'ascendant' => 0, 'midheaven' => 0, 'true_node' => 0, 'mean_apogee' => 0,
        ] as $key => $longitude) {
            $snapshot[$key] = ['longitude' => $longitude];
        }
        $points = (new AspectMatrixBuilder)->build($snapshot)['points'];
        $balance = (new ElementBalanceBuilder)->build($points);

        $this->assertSame(10, $balance['total']);
        $this->assertSame(['Fuego', 'Tierra', 'Aire', 'Agua'], array_column($balance['elements'], 'label'));
        $this->assertSame([3, 3, 3, 1], array_column($balance['elements'], 'count'));
        $this->assertSame([30.0, 30.0, 30.0, 10.0], array_column($balance['elements'], 'percentage'));
        $this->assertSame(['Sol', 'Luna', 'Mercurio'], $balance['elements'][0]['planets']);
    }

    public function test_sign_boundaries_and_wrapped_longitudes_use_the_same_normalization_as_the_matrix(): void
    {
        $points = (new AspectMatrixBuilder)->build([
            'sun' => ['longitude' => 29.999],
            'moon' => ['longitude' => 30],
            'mercury' => ['longitude' => 60],
            'venus' => ['longitude' => -0.001],
            'mars' => ['longitude' => 360],
        ])['points'];
        $balance = (new ElementBalanceBuilder)->build($points);

        $this->assertSame(5, $balance['total']);
        $this->assertSame([2, 1, 1, 1], array_column($balance['elements'], 'count'));
        $this->assertSame([40.0, 20.0, 20.0, 20.0], array_column($balance['elements'], 'percentage'));
    }

    public function test_empty_or_partial_data_is_not_counted_as_ten_planets(): void
    {
        $balance = (new ElementBalanceBuilder)->build([]);
        $this->assertSame(0, $balance['total']);
        $this->assertSame([0, 0, 0, 0], array_column($balance['elements'], 'count'));
        $this->assertSame([0.0, 0.0, 0.0, 0.0], array_column($balance['elements'], 'percentage'));

        $points = (new AspectMatrixBuilder)->build([
            'sun' => ['longitude' => 0],
            'moon' => ['longitude' => 120],
            'mars' => ['longitude' => 330],
        ])['points'];
        $balance = (new ElementBalanceBuilder)->build($points);
        $this->assertSame(3, $balance['total']);
        $this->assertSame([66.7, 0.0, 0.0, 33.3], array_column($balance['elements'], 'percentage'));
    }
}
