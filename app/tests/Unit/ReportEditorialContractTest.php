<?php

namespace Tests\Unit;

use App\Services\InteriorMapMaster;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\InteriorMapFixture;

final class ReportEditorialContractTest extends TestCase
{
    use InteriorMapFixture;

    public function test_master_hash_matches_the_actual_updated_manual(): void
    {
        $manual = dirname(__DIR__, 3).'/docs/Manual_Maestro_Universal_Informe_I_actualizado.docx';
        $this->assertSame(InteriorMapMaster::MANUAL_SHA256, strtoupper(hash_file('sha256', $manual)));
    }

    #[DataProvider('invalidKeywords')]
    public function test_keywords_require_five_to_ten_distinct_single_words(array $keywords): void
    {
        $content = self::chapter('sol');
        $content['concepts'][0]['keywords'] = $keywords;
        $this->expectException(RuntimeException::class);
        (new InteriorMapMaster)->validate($content);
    }

    public static function invalidKeywords(): array
    {
        return [
            [[]],
            [['Uno', 'Dos', 'Tres', 'Cuatro']],
            [array_fill(0, 11, 'Palabra')],
            [['Criterio', 'criterio', 'Autonomía', 'Equilibrio', 'Decisión']],
            [['Fuerza interior', 'Criterio', 'Autonomía', 'Equilibrio', 'Decisión']],
            [['Cuidado/amor', 'Criterio', 'Autonomía', 'Equilibrio', 'Decisión']],
            [['Auto-cuidado', 'Criterio', 'Autonomía', 'Equilibrio', 'Decisión']],
        ];
    }

    public function test_all_three_states_require_explanations_and_keywords(): void
    {
        $master = new InteriorMapMaster;
        foreach (array_keys(InteriorMapMaster::STATES) as $key) {
            $content = self::chapter('luna');
            unset($content['states'][$key]);
            try {
                $master->validate($content);
                $this->fail('Each state must exist.');
            } catch (RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $content = self::chapter('luna');
            $content['states'][$key]['keywords'] = ['Afecto'];
            try {
                $master->validate($content);
                $this->fail('Each state must have five to ten keywords.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('palabras clave', $exception->getMessage());
            }
        }
    }

    #[DataProvider('invalidProse')]
    public function test_voseo_enumeration_and_corrupt_characters_are_rejected(string $text): void
    {
        $content = self::chapter('sol');
        $content['states']['harmonious']['paragraphs'] = [$text];
        $this->expectException(RuntimeException::class);
        (new InteriorMapMaster)->validate($content);
    }

    public static function invalidProse(): array
    {
        return [['Vos podés elegir.'], ['Tenés recursos.'], ['1. Un rasgo enumerado.'], ['a) Un rasgo enumerado.'], ['Tu expresi?n puede cambiar.'], ["Una expresi\u{FFFD}n corrupta."]];
    }

    public function test_legitimate_questions_are_preserved(): void
    {
        $content = self::chapter('sol');
        $content['states']['harmonious']['paragraphs'] = ['¿Cómo eliges expresar tus recursos? Puedes contrastarlo con tu experiencia.'];
        $this->assertNull((new InteriorMapMaster)->validate($content));
    }
}
