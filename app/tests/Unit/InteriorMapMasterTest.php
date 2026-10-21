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
        $this->assertSame(['sign' => 'Cáncer', 'house' => 4, 'longitude' => 95.0, 'opposite' => 'Capricornio'], array_intersect_key($facts['sol'], array_flip(['sign', 'house', 'longitude', 'opposite'])));
        $this->assertSame([['planet' => 'moon', 'sign' => 'Sagitario', 'house' => 9]], $facts['sol']['rulers']['traditional']);
        $this->assertSame($facts['sol']['rulers']['traditional'], $facts['sol']['rulers']['modern']);
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

    public function test_modern_rulers_are_included_with_their_verified_positions(): void
    {
        $snapshot = self::snapshot();
        $snapshot['sun'] = ['longitude' => 215, 'sign' => 'scorpio'];
        $facts = (new InteriorMapMaster)->facts(new Chart([
            'configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus'],
            'snapshot' => $snapshot,
        ]));

        $this->assertSame([['planet' => 'mars', 'sign' => 'Capricornio', 'house' => 10]], $facts['sol']['rulers']['traditional']);
        $this->assertSame([
            ['planet' => 'mars', 'sign' => 'Capricornio', 'house' => 10],
            ['planet' => 'pluto', 'sign' => 'Escorpio', 'house' => 8],
        ], $facts['sol']['rulers']['modern']);
    }

    public function test_missing_ruler_positions_are_rejected_instead_of_invented(): void
    {
        $snapshot = self::snapshot();
        unset($snapshot['venus']);
        $this->expectExceptionMessage('Falta la posición verificada del regente venus');
        (new InteriorMapMaster)->facts(new Chart([
            'configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus'],
            'snapshot' => $snapshot,
        ]));
    }

    public function test_inconsistent_ruler_placements_are_rejected(): void
    {
        $master = new InteriorMapMaster;
        $facts = $master->facts(new Chart([
            'configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus'],
            'snapshot' => self::snapshot(),
        ]));
        $content = self::chapter('sol');
        $content['sign'][] = 'Venus está en Aries, casa I.';

        $this->expectExceptionMessage('posición verificada de un regente');
        $master->validateFacts($content, $facts, 'sol');
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
        $this->assertLessThanOrEqual(InteriorMapMaster::REPORT_MAX_WORDS, $total + 200);
        $this->assertArrayNotHasKey('ruler', $master->schema()['properties']);
        $this->assertSame(array_keys(InteriorMapMaster::TITLES), $master->schema()['required']);
        $this->assertStringNotContainsString('Pastora', $master->systemPrompt());
        $this->assertStringNotContainsString('{CHAPTER_MAX}', $master->systemPrompt());
        $this->assertStringContainsString('800–1800', $master->systemPrompt());
        $this->assertStringContainsString('4000 y 9500', $master->systemPrompt());
    }

    public function test_old_structure_and_empty_sections_are_rejected(): void
    {
        $content = self::chapter('sol');
        $content['ruler'] = ['Old content'];
        $this->expectExceptionMessage('exactamente las secciones');
        (new InteriorMapMaster)->validate($content);
    }

    public function test_chapter_can_use_the_expanded_word_budget_for_developed_examples(): void
    {
        $content = self::chapter('sol');
        $content['practice'][] = str_repeat('palabra ', 675);

        (new InteriorMapMaster)->validate($content);
        $this->assertGreaterThan(1400, (new InteriorMapMaster)->wordCount($content));
        $this->assertLessThanOrEqual(1800, (new InteriorMapMaster)->wordCount($content));
    }

    public function test_chapter_word_cap_is_enforced(): void
    {
        $content = self::chapter('sol');
        $content['practice'][] = str_repeat('palabra ', 1050);
        $this->expectExceptionMessage('Extensión de capítulo inválida');
        (new InteriorMapMaster)->validate($content);
    }

    public function test_chapter_with_1492_words_passes_validation(): void
    {
        $content = self::chapter('sol');
        $currentWords = (new InteriorMapMaster)->wordCount($content);
        $content['practice'][] = str_repeat('palabra ', 1492 - $currentWords);

        (new InteriorMapMaster)->validate($content);
        $this->assertSame(1492, (new InteriorMapMaster)->wordCount($content));
    }

    public function test_integration_with_1492_words_passes_validation(): void
    {
        $content = self::chapter('integration');
        $currentWords = (new InteriorMapMaster)->wordCount($content);
        $content['practice'][] = str_repeat('palabra ', 1492 - $currentWords);

        (new InteriorMapMaster)->validate($content, true);
        $this->assertSame(1492, (new InteriorMapMaster)->wordCount($content));
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
        $content['practice'][] = $content['function'][0];
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

    public function test_integration_rejects_a_theme_without_its_work_points(): void
    {
        $content = self::chapter('integration');
        unset($content['themes'][0]['work_points']);
        $this->expectExceptionMessage('Cada tema requiere');
        (new InteriorMapMaster)->validate($content, true);
    }

    public function test_integration_facts_are_checked_inside_nested_theme_paragraphs(): void
    {
        $master = new InteriorMapMaster;
        $facts = $master->facts(new Chart(['configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus'], 'snapshot' => self::snapshot()]));
        $content = self::chapter('integration');
        $content['themes'][0]['paragraphs'][] = 'Tu Sol en Libra describe tu identidad.';
        $this->expectExceptionMessage('contradice una posición canónica');
        $master->validateFacts($content, $facts, null);
    }

    public function test_summary_requires_all_four_labels(): void
    {
        $content = self::chapter('sol');
        $content['summary'][0] = 'Una síntesis sin distinguir el don.';
        $this->expectExceptionMessage('La síntesis requiere');
        (new InteriorMapMaster)->validate($content);
    }

    public function test_integration_rejects_unstructured_themes(): void
    {
        $content = self::chapter('integration');
        $content['themes'] = 'Un bloque sin temas diferenciados.';
        $this->expectExceptionMessage('lista no vacía');
        (new InteriorMapMaster)->validate($content, true);
    }

    public function test_each_state_requires_exactly_five_behaviours_in_schema_and_validation(): void
    {
        $master = new InteriorMapMaster;
        foreach (['harmony', 'deficit', 'excess'] as $state) {
            $this->assertSame(5, $master->schema()['properties'][$state]['minItems']);
            $this->assertSame(5, $master->schema()['properties'][$state]['maxItems']);
            foreach ([4, 6] as $count) {
                $content = self::chapter('sol');
                $content[$state] = array_fill(0, $count, 'Reconocer una necesidad y expresarla con claridad.');
                try {
                    $master->validate($content);
                    $this->fail('An invalid state count was accepted.');
                } catch (\RuntimeException $exception) {
                    $this->assertStringContainsString('exactamente cinco', $exception->getMessage());
                }
            }
        }
    }

    public function test_general_function_cannot_start_with_a_sign_interpretation(): void
    {
        $content = self::chapter('sol');
        $content['function'][0] = 'El Sol en Cáncer explica cómo te expresas.';
        $this->expectExceptionMessage('función general');
        (new InteriorMapMaster)->validate($content);
    }

    public function test_element_framework_and_technical_jargon_are_rejected(): void
    {
        foreach (['Fuego: actuar con iniciativa.', 'El elemento tierra organiza tu experiencia.', 'Alquimia emocional', 'Impulso ariano', 'Mecanismos operativos', 'Indicadores de validación'] as $text) {
            $content = self::chapter('sol');
            $content['sign'][] = $text;
            try {
                (new InteriorMapMaster)->validate($content);
                $this->fail('An obsolete writing framework was accepted.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('ajeno al método', $exception->getMessage());
            }
        }
    }
}
