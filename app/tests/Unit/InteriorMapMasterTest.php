<?php

namespace Tests\Unit;

use App\Models\Chart;
use App\Services\AspectMatrixBuilder;
use App\Services\InteriorMapMaster;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\InteriorMapFixture;

final class InteriorMapMasterTest extends TestCase
{
    use InteriorMapFixture;

    private function facts(?array $snapshot = null): array
    {
        return (new InteriorMapMaster)->facts(new Chart([
            'configuration' => ['zodiac' => 'tropical', 'houses' => 'placidus'],
            'snapshot' => $snapshot ?? self::snapshot(),
        ]));
    }

    public function test_positions_houses_opposites_and_all_occupants_use_the_current_snapshot(): void
    {
        $facts = $this->facts();
        $this->assertSame('Cáncer', $facts['sol']['sign']);
        $this->assertSame(4, $facts['sol']['house']);
        $this->assertSame('Capricornio', $facts['sol']['opposite']);
        $this->assertSame('moon', $facts['sol']['rulers']['traditional'][0]['planet']);
        $this->assertSame('Sagitario', $facts['sol']['rulers']['traditional'][0]['sign']);
        $this->assertCount(12, $facts['positions']);
        $this->assertSame(['venus', 'pluto'], $facts['houses'][8]['occupants']);
        $this->assertSame(['mercury'], $facts['houses'][3]['occupants']);
        $this->assertSame('unknown', $facts['technical']['time_precision']);
        $this->assertTrue($facts['technical']['angles_houses_conditional']);
        $this->assertSame(6.0, $facts['technical']['maximum_orb']);
        $this->assertSame(150.0, $facts['lunar_phase']['elongation_degrees']);
    }

    public function test_cusp_sign_is_not_substituted_for_the_planet_sign(): void
    {
        $snapshot = self::snapshot();
        $snapshot['houses'][4]['longitude'] = 89;
        $facts = $this->facts($snapshot);
        $this->assertSame('Géminis', $facts['houses'][4]['sign']);
        $this->assertSame('Cáncer', $facts['sol']['sign']);
        $this->assertSame(4, $facts['sol']['house']);
    }

    public function test_rounding_difference_at_an_angle_does_not_assign_it_to_the_previous_house(): void
    {
        $snapshot = self::snapshot();
        $snapshot['houses'][1]['longitude'] = 0.000001;
        $snapshot['houses'][7]['longitude'] = 180.000001;
        $facts = $this->facts($snapshot);
        $this->assertSame(1, $facts['ascendente']['house']);
        $this->assertSame(7, $facts['descendente']['house']);
        $this->assertSame(0.0, $facts['ascendente']['distance_from_house_cusp']);
    }

    public function test_domicile_does_not_invent_an_external_dispositor(): void
    {
        $snapshot = self::snapshot();
        $snapshot['sun'] = ['longitude' => 125, 'sign' => 'leo'];
        $facts = $this->facts($snapshot);
        $this->assertTrue($facts['sol']['self_disposed_traditional']);
        $this->assertSame(['domicilio'], $facts['sol']['major_dignities']);
        $this->assertSame('sun', $facts['sol']['rulers']['traditional'][0]['planet']);
    }

    public function test_major_classical_dignities_and_modern_regencies_are_distinct(): void
    {
        $snapshot = self::snapshot();
        $snapshot['sun'] = ['longitude' => 185, 'sign' => 'libra'];
        $facts = $this->facts($snapshot);
        $this->assertSame(['caída'], $facts['sol']['major_dignities']);
        $this->assertSame(['detrimento'], $facts['positions']['venus']['major_dignities']);
        $this->assertSame(['exaltación'], $facts['positions']['mars']['major_dignities']);
        $this->assertSame(['domicilio'], $facts['positions']['jupiter']['major_dignities']);
        $this->assertSame(['mars'], $facts['positions']['venus']['rulership']['traditional']);
        $this->assertSame(['mars', 'pluto'], $facts['positions']['venus']['rulership']['modern']);
        $this->assertSame([], $facts['positions']['pluto']['major_dignities']);
    }

    public function test_missing_cusp_is_rejected_instead_of_filled(): void
    {
        $this->expectExceptionMessage('Falta la cúspide');
        $this->facts([]);
    }

    public function test_missing_non_ruler_planet_is_not_treated_as_an_empty_house(): void
    {
        $snapshot = self::snapshot();
        unset($snapshot['mercury']);
        $this->expectExceptionMessage('posición verificada de mercury');
        $this->facts($snapshot);
    }

    public function test_missing_ruler_is_rejected(): void
    {
        $snapshot = self::snapshot();
        unset($snapshot['venus']);
        $this->expectExceptionMessage('posición verificada de venus');
        $this->facts($snapshot);
    }

    public function test_inconsistent_sign_is_rejected(): void
    {
        $snapshot = self::snapshot();
        $snapshot['sun']['sign'] = 'libra';
        $this->expectExceptionMessage('Signo y longitud inconsistentes');
        $this->facts($snapshot);
    }

    public function test_non_placidus_configuration_is_not_silently_reinterpreted(): void
    {
        $this->expectExceptionMessage('requiere zodiaco tropical');
        (new InteriorMapMaster)->facts(new Chart(['configuration' => ['zodiac' => 'tropical', 'houses' => 'koch'], 'snapshot' => self::snapshot()]));
    }

    public function test_aspect_threshold_and_exact_orbs_do_not_use_rounded_visual_values(): void
    {
        $builder = new AspectMatrixBuilder;
        $aspect = $builder->between(359, 65)[0];
        $this->assertSame('sextile', $aspect['key']);
        $this->assertSame(6.0, $aspect['exact_orb']);
        $this->assertSame([], $builder->between(359, 65.0001));
        $this->assertEqualsWithDelta(0.001, $builder->between(1, 61.001)[0]['exact_orb'], 0.000001);
        foreach ($this->facts()['aspects'] as $fact) {
            $this->assertNull($fact['applying_or_separating']);
            $this->assertFalse($fact['first'] === 'ascendant' && $fact['second'] === 'descendant');
        }
    }

    public function test_schema_has_open_concepts_with_explained_characteristics_and_no_old_sections(): void
    {
        $master = new InteriorMapMaster;
        $this->assertSame(['concepts'], $master->schema()['required']);
        $this->assertSame(['title', 'characteristics'], $master->schema()['properties']['concepts']['items']['required']);
        $this->assertSame(InteriorMapMaster::CHAPTERS, $master->auditSchema()['properties']['replacements']['items']['properties']['chapter']['enum']);
        $this->assertStringContainsString('siete páginas', $master->systemPrompt());
        $this->assertStringContainsString('matriz interna', $master->systemPrompt());
        $this->assertStringContainsString('tradicionales prioritarias', $master->systemPrompt());
        $this->assertStringNotContainsString('Pastora', $master->systemPrompt());
        $this->assertStringNotContainsString('Libra en casa VI', $master->systemPrompt());
        foreach (InteriorMapMaster::CHAPTERS as $chapter) {
            $master->validate(self::chapter($chapter));
        }
    }

    public function test_position_format_carries_rounded_seconds_across_sign_and_zodiac_boundaries(): void
    {
        $builder = new AspectMatrixBuilder;
        $this->assertSame('Tauro 0° 0\' 0.0"', $builder->formatLongitude(29.999999));
        $this->assertSame('Aries 0° 0\' 0.0"', $builder->formatLongitude(359.999999));
    }

    public function test_short_supported_blocks_are_not_padded_to_a_fixed_minimum(): void
    {
        $master = new InteriorMapMaster;
        $content = self::chapter('sol');
        $this->assertLessThan(780, $master->wordCount($content));
        $master->validate($content);
    }

    public function test_technical_word_cap_remains_enforced(): void
    {
        $content = self::chapter('sol');
        $content['concepts'][0]['characteristics'][0]['paragraphs'][] = str_repeat('palabra ', 6000);
        $this->expectExceptionMessage('Extensión de bloque inválida');
        (new InteriorMapMaster)->validate($content);
    }

    public function test_old_schema_and_unexplained_lists_are_rejected(): void
    {
        $this->expectExceptionMessage('exclusivamente conceptos');
        (new InteriorMapMaster)->validate(['harmony' => ['adjetivo'], 'summary' => ['cierre']]);
    }

    public function test_each_characteristic_requires_paragraphs(): void
    {
        $content = self::chapter('sol');
        $content['concepts'][0]['characteristics'][0]['paragraphs'] = [];
        $this->expectExceptionMessage('lista no vacía de párrafos');
        (new InteriorMapMaster)->validate($content);
    }

    public function test_a_concept_cannot_exceed_eight_characteristics(): void
    {
        $content = self::chapter('sol');
        $template = $content['concepts'][0]['characteristics'][0];
        $content['concepts'][0]['characteristics'] = array_map(static fn (int $n): array => ['title' => 'Rasgo propio '.$n] + $template, range(1, 9));
        $this->expectExceptionMessage('como máximo 8 características');
        (new InteriorMapMaster)->validate($content);
    }

    public function test_eight_characteristics_are_accepted(): void
    {
        $content = self::chapter('sol');
        $template = $content['concepts'][0]['characteristics'][0];
        $content['concepts'][0]['characteristics'] = array_map(static fn (int $n): array => ['title' => 'Rasgo propio '.$n] + $template, range(1, 8));
        $this->assertNull((new InteriorMapMaster)->validate($content));
    }

    #[DataProvider('internalFieldNames')]
    public function test_internal_data_field_names_are_rejected(string $text): void
    {
        $content = self::chapter('sol');
        $content['concepts'][0]['characteristics'][0]['paragraphs'][] = $text;
        $this->expectExceptionMessage('nombres internos de datos');
        (new InteriorMapMaster)->validate($content);
    }

    public static function internalFieldNames(): array
    {
        return array_map(static fn (string $text): array => [$text], [
            'Según positions, tu Sol destaca.',
            'Los rulers indican una cadena de regencias.',
            'El snapshot confirma la casa.',
            'Los occupants de la casa VI acompañan.',
            'Como se vio en current_report, el tema vuelve.',
            'El maximum_orb permite el contacto.',
            'La time_precision es exacta.',
        ]);
    }

    public function test_prompt_carries_the_corrective_editorial_contract(): void
    {
        $prompt = (new InteriorMapMaster)->systemPrompt();
        $this->assertStringContainsString('I El Sol; II La Luna; III El Ascendente; IV El Descendente; V Los opuestos complementarios', $prompt);
        $this->assertStringNotContainsString('Sol natal', $prompt);
        $this->assertStringContainsString('entre cinco y ocho características', $prompt);
        $this->assertStringContainsString('dos o tres párrafos', $prompt);
        $this->assertStringContainsString('nunca en porcentajes', $prompt);
        $this->assertStringContainsString('10. Integra', $prompt);
        $this->assertStringContainsString('DIFERENCIACIÓN ENTRE BLOQUES', $prompt);
    }

    #[DataProvider('excludedHeadings')]
    public function test_introductions_summaries_and_closings_are_rejected(string $heading): void
    {
        $content = self::chapter('sol');
        $content['concepts'][0]['title'] = $heading;
        $this->expectExceptionMessage('no admite introducciones');
        (new InteriorMapMaster)->validate($content);
    }

    public static function excludedHeadings(): array
    {
        return array_map(static fn (string $title): array => [$title], ['Introducción', 'Resumen solar', 'Síntesis final', 'Conclusión', 'Cierre', 'Prólogo']);
    }

    #[DataProvider('contradictoryClaims')]
    public function test_contradictions_are_checked_in_any_nested_characteristic(string $claim, string $error): void
    {
        $content = self::chapter('opposites');
        $content['concepts'][0]['characteristics'][0]['paragraphs'][] = $claim;
        $this->expectExceptionMessage($error);
        (new InteriorMapMaster)->validateFacts($content, $this->facts());
    }

    public static function contradictoryClaims(): array
    {
        return [
            ['Tu Sol en Libra expresa tu identidad.', 'posición canónica'],
            ['Plutón está en Aries, casa I.', 'posición canónica'],
            ['La Luna, regente de Cáncer, está situada en casa VIII.', 'casa verificada'],
            ['Júpiter en Piscis, casa VII.', 'casa verificada'],
            ['La oposición Sol-Luna señala una relación.', 'aspecto no verificado'],
            ['Sin embargo, tu Sol en Libra expresa tu identidad.', 'posición canónica'],
            ['La polaridad no exige nada. Tu Sol en Libra expresa tu identidad.', 'posición canónica'],
            ['No es un diagnóstico, pero tu Sol en Libra expresa tu identidad.', 'posición canónica'],
            ['Sin prisa, tu Sol en Libra expresa tu identidad.', 'posición canónica'],
            ['No solo tu Sol en Libra resulta relevante.', 'posición canónica'],
            ['No exige Luna en Acuario, pero tu Sol en Libra expresa tu identidad.', 'posición canónica'],
            ['La Luna está situada en Acuario.', 'posición canónica'],
            ['Plutón se encuentra ubicado en Aries.', 'posición canónica'],
            ['El Sol está en oposición a la Luna.', 'aspecto no verificado'],
            ['No hay oposición Sol-Luna, pero Júpiter en Piscis, casa VII, también importa.', 'casa verificada'],
        ];
    }

    #[DataProvider('nonAssertedClaims')]
    public function test_negated_or_hypothetical_mentions_are_not_read_as_positions(string $text): void
    {
        $content = self::chapter('opposites');
        $content['concepts'][0]['characteristics'][0]['paragraphs'][] = $text;
        $this->assertNull((new InteriorMapMaster)->validateFacts($content, $this->facts()));
    }

    public static function nonAssertedClaims(): array
    {
        return array_map(static fn (string $text): array => [$text], [
            'Técnicamente: la polaridad no exige Luna en Acuario ni Sol en Libra.',
            'No hay una oposición Sol-Luna dentro del orbe utilizado.',
            'Leerlo como si Júpiter en Piscis, casa VII, fuera un dato sería un error.',
            'La polaridad no requiere una Luna en Acuario.',
            'El complemento no equivale a una Luna en Acuario.',
            'Tampoco se exige Luna en Acuario.',
            'La casa VI como taller (con Sol, Venus, Plutón), casa VII ocupada por Saturno.',
            'La Luna, junto con Júpiter en Piscis, casa XII.',
        ]);
    }

    public function test_negation_does_not_leak_between_paragraphs(): void
    {
        $content = self::chapter('opposites');
        $content['concepts'][0]['characteristics'][0]['paragraphs'] = [
            'La polaridad no exige',
            'Tu Sol en Libra expresa tu identidad.',
        ];
        $this->expectExceptionMessage('posición canónica');
        (new InteriorMapMaster)->validateFacts($content, $this->facts());
    }

    public function test_separate_fields_are_not_combined_into_position_claims(): void
    {
        $content = self::chapter('sol');
        $content['concepts'][0]['title'] = 'El Sol';
        $content['concepts'][0]['characteristics'][0]['title'] = 'En Libra como principio complementario';
        $this->assertNull((new InteriorMapMaster)->validateFacts($content, $this->facts()));
    }

    public function test_other_house_rulers_are_not_mistaken_for_the_current_house(): void
    {
        $content = self::chapter('sol');
        $content['concepts'][0]['characteristics'][0]['paragraphs'][] = 'La Luna, regente de Cáncer, está situada en casa IX.';
        $this->assertNull((new InteriorMapMaster)->validateFacts($content, $this->facts(), 'sol'));
    }

    public function test_all_closing_and_technical_titles_are_reported_together(): void
    {
        $content = self::chapter('sol');
        $content['concepts'][0]['characteristics'][0]['title'] = 'Introducción técnica y posición exacta';
        $content['concepts'][1]['characteristics'][1]['title'] = 'Cómo se modula la voluntad: síntesis de la cadena';
        try {
            (new InteriorMapMaster)->validate($content);
            $this->fail('Se esperaba un rechazo.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('«Introducción técnica y posición exacta»', $exception->getMessage());
            $this->assertStringContainsString('síntesis de la cadena»', $exception->getMessage());
        }
    }

    #[DataProvider('technicalTitles')]
    public function test_technical_titles_are_rejected(string $title): void
    {
        $content = self::chapter('sol');
        $content['concepts'][0]['characteristics'][0]['title'] = $title;
        $this->expectExceptionMessage('lenguaje llano');
        (new InteriorMapMaster)->validate($content);
    }

    public static function technicalTitles(): array
    {
        return array_map(static fn (string $title): array => [$title], [
            'Sextil exacto a la Luna a 5° 17′',
            "Cómo influye la dignidad 'caída'",
            'Cuadratura a Neptuno — orbe 2.3',
        ]);
    }

    public function test_title_with_parentheses_is_rejected_and_its_plain_rewrite_is_accepted(): void
    {
        $master = new InteriorMapMaster;
        $content = self::chapter('sol');
        $content['concepts'][0]['characteristics'][0]['title'] = 'Cómo se activa tu energía en lo cotidiano (casa VI)';
        try {
            $master->validate($content);
            $this->fail('Se esperaba un rechazo por paréntesis.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('ni paréntesis', $exception->getMessage());
            $this->assertStringContainsString('«Cómo se activa tu energía en lo cotidiano (casa VI)»', $exception->getMessage());
        }
        $content['concepts'][0]['characteristics'][0]['title'] = 'Cómo se activa tu energía en lo cotidiano';
        $this->assertNull($master->validate($content));
    }

    public function test_analysis_steps_advice_and_unsupported_inferences_are_rejected_as_titles(): void
    {
        $master = new InteriorMapMaster;
        $titles = [
            'La cúspide en Libra y la relevancia de Venus' => 'pasos del análisis',
            'Qué implica que el Sol esté en la casa seis' => 'pasos del análisis',
            'Lecciones de autonomía ligadas a la cadena dispositiva' => 'pasos del análisis',
            'Ejercicios breves para entrenar la complementariedad' => 'ejercicios, prácticas',
            'La voz y el gesto como extensiones de seguridad' => 'rasgos corporales',
            'Cómo se manifiesta la falta de impulso y qué necesitas' => 'carencias',
            'Atracción por personas que gestionan recursos' => 'otras personas',
        ];
        foreach ($titles as $title => $message) {
            $content = self::chapter('sol');
            $content['concepts'][0]['characteristics'][0]['title'] = $title;
            try {
                $master->validate($content);
                $this->fail("Se esperaba un rechazo para «{$title}».");
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
                $this->assertStringContainsString("«{$title}»", $exception->getMessage());
            }
        }
        $content['concepts'][0]['characteristics'][0]['title'] = 'Identidad que se fortalece con el hacer útil';
        $this->assertNull($master->validate($content));
    }

    public function test_decimal_orbs_in_paragraphs_are_rejected(): void
    {
        $content = self::chapter('sol');
        $content['concepts'][0]['characteristics'][0]['paragraphs'][] = 'El contacto tiene un orbe de 0.096° con la Luna.';
        $this->expectExceptionMessage('sin decimales');
        (new InteriorMapMaster)->validate($content);
    }

    public function test_duplicate_paragraphs_are_detected_across_blocks(): void
    {
        $first = self::chapter('sol');
        $second = self::chapter('luna');
        $second['concepts'][0]['characteristics'][0]['paragraphs'] = $first['concepts'][0]['characteristics'][0]['paragraphs'];
        $this->expectExceptionMessage('repite un párrafo');
        (new InteriorMapMaster)->assertNoDuplicateParagraphs([$first, $second]);
    }
}
