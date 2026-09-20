<?php

namespace Tests\Unit;

use App\Services\NatalWheelRenderer;
use PHPUnit\Framework\TestCase;

class NatalWheelRendererTest extends TestCase
{
    public function test_it_renders_a_svg_wheel_with_houses_and_key_points(): void
    {
        $snapshot = [
            'sun' => ['sign' => 'leo', 'degrees' => 10, 'minutes' => 0, 'seconds' => 0, 'longitude' => 130.0],
            'moon' => ['sign' => 'cancer', 'degrees' => 5, 'minutes' => 0, 'seconds' => 0, 'longitude' => 95.0],
            'ascendant' => ['sign' => 'pisces', 'degrees' => 15, 'minutes' => 0, 'seconds' => 0, 'longitude' => 345.0],
            'midheaven' => ['sign' => 'sagittarius', 'degrees' => 22, 'minutes' => 0, 'seconds' => 0, 'longitude' => 202.0],
            'houses' => [
                1 => ['sign' => 'pisces', 'degrees' => 15, 'minutes' => 0, 'seconds' => 0, 'longitude' => 345.0],
                2 => ['sign' => 'taurus', 'degrees' => 0, 'minutes' => 30, 'seconds' => 0, 'longitude' => 30.0],
                3 => ['sign' => 'taurus', 'degrees' => 29, 'minutes' => 54, 'seconds' => 0, 'longitude' => 59.9],
                4 => ['sign' => 'gemini', 'degrees' => 22, 'minutes' => 20, 'seconds' => 0, 'longitude' => 82.3],
                5 => ['sign' => 'cancer', 'degrees' => 13, 'minutes' => 30, 'seconds' => 0, 'longitude' => 103.5],
                6 => ['sign' => 'leo', 'degrees' => 8, 'minutes' => 25, 'seconds' => 0, 'longitude' => 128.4],
                7 => ['sign' => 'virgo', 'degrees' => 15, 'minutes' => 0, 'seconds' => 0, 'longitude' => 165.0],
                8 => ['sign' => 'scorpio', 'degrees' => 0, 'minutes' => 30, 'seconds' => 0, 'longitude' => 210.0],
                9 => ['sign' => 'scorpio', 'degrees' => 29, 'minutes' => 54, 'seconds' => 0, 'longitude' => 239.9],
                10 => ['sign' => 'sagittarius', 'degrees' => 22, 'minutes' => 20, 'seconds' => 0, 'longitude' => 262.3],
                11 => ['sign' => 'capricorn', 'degrees' => 13, 'minutes' => 30, 'seconds' => 0, 'longitude' => 283.5],
                12 => ['sign' => 'aquarius', 'degrees' => 8, 'minutes' => 25, 'seconds' => 0, 'longitude' => 308.4],
            ],
        ];

        $renderer = new NatalWheelRenderer();
        $svg = $renderer->render($snapshot);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('sign-boundary-1', $svg);
        $this->assertStringContainsString('sign-boundary-12', $svg);
        $this->assertStringContainsString('house-cusp-1', $svg);
        $this->assertStringContainsString('house-cusp-point-1', $svg);
        $this->assertStringContainsString('point-sun', $svg);
        $this->assertStringContainsString('point-moon', $svg);
        $this->assertStringContainsString('point-ascendant', $svg);
        $this->assertStringContainsString('aspect-line', $svg);
        $this->assertStringContainsString('♈', $svg);
        $this->assertStringContainsString('Aries', $svg);
    }
}
