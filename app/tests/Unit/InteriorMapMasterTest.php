<?php

namespace Tests\Unit;

use App\Models\Chart;
use App\Services\InteriorMapMaster;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\InteriorMapFixture;

final class InteriorMapMasterTest extends TestCase
{
    use InteriorMapFixture;

    public function test_positions_houses_and_opposites_are_derived_without_example_defaults(): void
    {
        $chart = new Chart(['configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus'], 'snapshot' => self::snapshot()]);
        $facts = (new InteriorMapMaster)->facts($chart);
        $this->assertSame(['sign' => 'Cáncer', 'house' => 4, 'longitude' => 95.0, 'opposite' => 'Capricornio'], $facts['sol']);
        $this->assertSame('Sagitario', $facts['luna']['sign']);
        $this->assertSame(1, $facts['ascendente']['house']);
        $this->assertSame(7, $facts['descendente']['house']);
    }

    public function test_missing_data_are_rejected_instead_of_filled(): void
    {
        $chart = new Chart(['configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus'], 'snapshot' => []]);
        $this->expectExceptionMessage('Falta la cúspide');
        (new InteriorMapMaster)->facts($chart);
    }

    public function test_non_placidus_protocol_is_rejected(): void
    {
        $this->expectExceptionMessage('requiere zodiaco tropical');
        (new InteriorMapMaster)->facts(new Chart(['configuration' => ['zodiac' => 'tropical', 'houses' => 'koch'], 'snapshot' => self::snapshot()]));
    }

    public function test_inconsistent_sign_is_rejected(): void
    {
        $snapshot = self::snapshot();
        $snapshot['sun']['sign'] = 'libra';
        $this->expectExceptionMessage('Signo y longitud inconsistentes');
        (new InteriorMapMaster)->facts(new Chart(['configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus'], 'snapshot' => $snapshot]));
    }

    public function test_schema_and_total_word_budget_follow_only_the_new_master(): void
    {
        $master = new InteriorMapMaster;
        $total = 0;
        foreach ([...InteriorMapMaster::DOORS, 'integration'] as $chapter) {
            $content = self::chapter($chapter);
            $master->validate($content, $chapter === 'integration');
            $total += $master->wordCount($content);
        }
        $this->assertGreaterThanOrEqual(4000, $total);
        $this->assertLessThanOrEqual(5500, $total + 200);
        $this->assertArrayNotHasKey('ruler', $master->schema()['properties']);
        $this->assertSame(array_keys(InteriorMapMaster::TITLES), $master->schema()['required']);
        $this->assertStringNotContainsString('Pastora', $master->systemPrompt());
    }

    public function test_old_structure_and_empty_sections_are_rejected(): void
    {
        $content = self::chapter('sol');
        $content['ruler'] = ['Old content'];
        $this->expectExceptionMessage('exactamente las secciones');
        (new InteriorMapMaster)->validate($content);
    }

    public function test_chapter_word_cap_is_enforced(): void
    {
        $content = self::chapter('sol');
        $content['practice'][] = str_repeat('palabra ', 400);
        $this->expectExceptionMessage('Extensión de capítulo inválida');
        (new InteriorMapMaster)->validate($content);
    }

    public function test_wrong_canonical_claim_in_narrative_is_rejected(): void
    {
        $master = new InteriorMapMaster;
        $facts = $master->facts(new Chart(['configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus'], 'snapshot' => self::snapshot()]));
        $content = self::chapter('sol');
        $content['sign'][] = 'Tu Sol en Libra te invita a reflexionar.';
        $this->expectExceptionMessage('contradice una posición canónica');
        $master->validateFacts($content, $facts, 'sol');
    }

    public function test_duplicate_paragraphs_are_not_accepted_as_a_complete_report(): void
    {
        $content = self::chapter('sol');
        $content['practice'][] = $content['harmony'][0];
        $this->expectExceptionMessage('repite un párrafo');
        (new InteriorMapMaster)->assertNoDuplicateParagraphs([$content]);
    }

    public function test_all_twelve_signs_have_their_exact_complementary_opposite(): void
    {
        $signs = array_keys(InteriorMapMaster::SIGNS);
        $master = new InteriorMapMaster;
        foreach ($signs as $index => $sign) {
            $snapshot = self::snapshot();
            $snapshot['sun'] = ['longitude' => $index * 30 + 5, 'sign' => $sign];
            $facts = $master->facts(new Chart(['configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus'], 'snapshot' => $snapshot]));
            $this->assertSame(InteriorMapMaster::SIGNS[$signs[($index + 6) % 12]], $facts['sol']['opposite']);
        }
    }
}
