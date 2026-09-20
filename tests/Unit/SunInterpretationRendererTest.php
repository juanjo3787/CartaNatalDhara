<?php

namespace Tests\Unit;

use App\Services\SunInterpretationRenderer;
use PHPUnit\Framework\TestCase;

class SunInterpretationRendererTest extends TestCase
{
    public function test_it_builds_a_complete_sun_reading_for_one_chart(): void
    {
        $snapshot = [
            'sun' => [
                'sign' => 'taurus',
                'degrees' => 18,
                'minutes' => 12,
                'seconds' => 5,
                'longitude' => 168.20,
            ],
            'ascendant' => [
                'sign' => 'sagittarius',
                'degrees' => 8,
                'minutes' => 25,
                'seconds' => 12,
            ],
            'houses' => [
                1 => ['sign' => 'virgo'],
                5 => ['sign' => 'capricorn'],
            ],
        ];

        $renderer = new SunInterpretationRenderer();
        $reading = $renderer->render('María', $snapshot);

        $this->assertArrayHasKey('shared_intro', $reading);
        $this->assertArrayHasKey('function', $reading);
        $this->assertArrayHasKey('sign', $reading);
        $this->assertArrayHasKey('house', $reading);
        $this->assertArrayHasKey('closing', $reading);

        $this->assertStringContainsString('María', $reading['shared_intro']);
        $this->assertStringContainsString('Tauro', $reading['sign']);
        $this->assertStringContainsString('Sagitario', $reading['house']);
        $this->assertStringContainsString('sol', strtolower($reading['function']));
    }
}
