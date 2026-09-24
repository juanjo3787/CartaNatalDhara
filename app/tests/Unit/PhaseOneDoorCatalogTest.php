<?php

namespace Tests\Unit;

use App\Services\PhaseOneDoorCatalog;
use PHPUnit\Framework\TestCase;

class PhaseOneDoorCatalogTest extends TestCase
{
    public function test_each_phase_one_door_has_the_complete_editorial_structure(): void
    {
        $context = [
            'subject' => 'La Luna',
            'sign' => 'Leo',
            'degrees' => 5,
            'minutes' => 17,
            'seconds' => 47,
            'house' => 4,
            'house_sign' => 'Cáncer',
            'rulers' => 'el Sol',
            'ruler_sign' => 'Libra',
            'ruler_house' => 6,
            'name' => 'Pastora',
        ];

        $catalog = new PhaseOneDoorCatalog;
        $expectedBlocks = [
            'shared_intro', 'function', 'sign', 'house', 'ruler',
            'integration', 'harmony', 'deficit', 'excess', 'harmonization', 'closing',
        ];

        foreach (['sol', 'luna', 'ascendente', 'descendente'] as $door) {
            $blocks = $catalog->blocks($door, $context);

            $this->assertSame($expectedBlocks, array_keys($blocks));
            foreach ($blocks as $paragraphs) {
                $this->assertGreaterThanOrEqual(3, count($paragraphs));
                $this->assertNotEmpty(implode(' ', $paragraphs));
            }
        }
    }

    public function test_catalog_adapts_identity_and_preserves_state_correspondence_for_another_chart(): void
    {
        $context = [
            'subject' => 'El Sol',
            'sign' => 'Aries',
            'degrees' => 12,
            'minutes' => 4,
            'seconds' => 8,
            'house' => 10,
            'house_sign' => 'Capricornio',
            'rulers' => 'Marte',
            'ruler_sign' => 'Géminis',
            'ruler_house' => 3,
            'name' => 'Lucía',
        ];

        $blocks = (new PhaseOneDoorCatalog)->blocks('sol', $context);

        $this->assertStringNotContainsString('Pastora', implode(' ', $blocks['function']));
        $this->assertStringContainsString('Lucía', $blocks['function'][0]);
        $this->assertCount(7, array_filter(explode('. ', $blocks['deficit'][4])));
        $this->assertCount(7, array_filter(explode('. ', $blocks['excess'][4])));
        $this->assertStringContainsString('Aries', implode(' ', $blocks['sign']));
        $this->assertStringContainsString('casa 10', implode(' ', $blocks['house']));
    }
}
