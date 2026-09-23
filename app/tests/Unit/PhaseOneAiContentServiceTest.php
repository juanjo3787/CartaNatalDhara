<?php

namespace Tests\Unit;

use App\Contracts\AiTextGenerator;
use App\Services\PhaseOneAiContentService;
use App\Services\PhaseOnePromptBuilder;
use Tests\TestCase;

class PhaseOneAiContentServiceTest extends TestCase
{
    public function test_it_builds_a_dossier_prompt_with_all_required_blocks(): void
    {
        $builder = new PhaseOnePromptBuilder();
        $prompts = $builder->build('descendente', [
            'question' => '¿Qué aprendo a través de mis relaciones?',
            'subject' => 'El Descendente',
            'sign' => 'Escorpio',
            'house' => 7,
            'rulers' => 'Marte y Plutón',
            'previous_doors' => ['sol' => ['ruler' => 'Venus ya explicado.']],
            'introduced_rulers' => ['venus' => 'sol'],
        ]);

        $this->assertStringContainsString('deseo, petición, acuerdo y norma', $prompts['system']);
        $this->assertStringContainsString('INSTRUCCIONES_CONTINUIDAD_DESCENDENTE_FASE_1.docx', $prompts['system']);
        $this->assertStringContainsString('descendente', strtolower($prompts['user']));
        $this->assertStringContainsString('Venus ya explicado', $prompts['user']);
        $this->assertStringContainsString('regentes_ya_presentados', $prompts['user']);
        $this->assertStringContainsString('No des una explicación genérica de astrología', $prompts['system']);
        $this->assertStringContainsString('No generes la ficha repetitiva Característica/Desarrollo/Pauta/Ejemplo', $prompts['system']);
        $this->assertStringContainsString('lista_de_comprobacion_antes_de_responder', $prompts['user']);
        $this->assertSame(10, count($builder->blocks('descendente')));
        $this->assertSame(11, count($builder->blocks('sol')));
    }

    public function test_it_builds_a_deep_solar_prompt_with_explicit_structure(): void
    {
        $prompts = (new PhaseOnePromptBuilder())->build('sol', [
            'question' => '¿Qué quiero aportar y elegir?',
            'subject' => 'El Sol',
            'sign' => 'Libra',
            'house' => 6,
        ]);

        $this->assertStringContainsString('Añade una armonización completa', $prompts['system']);
        $this->assertStringContainsString('La profundidad es obligatoria', $prompts['system']);
        $this->assertStringContainsString('Esquema JSON obligatorio', $prompts['system']);
        $this->assertStringContainsString('No agrupes Característica + Desarrollo + Pauta + Ejemplo', $prompts['system']);
        $this->assertStringContainsString('Regla de no repetición endurecida', $prompts['system']);
        $this->assertStringContainsString('¿Qué quiero aportar y elegir?', $prompts['user']);
        $this->assertStringContainsString('exactamente 3 strings independientes', $prompts['user']);
        $this->assertStringContainsString('El resultado debe tener la profundidad, personalización', $prompts['system']);
        $this->assertStringContainsString('control_calidad_dossier', $prompts['user']);
        $this->assertStringContainsString('Características que puedes observar', $prompts['system']);
        $this->assertStringContainsString('No escribas "Características que puedes observer"', $prompts['system']);
        $this->assertStringContainsString('Pautas y consideraciones para recuperar una medida adecuada', $prompts['user']);
    }

    public function test_it_builds_the_lunar_user_prompt_with_ten_blocks_and_venus_continuity(): void
    {
        $prompts = (new PhaseOnePromptBuilder())->build('luna', [
            'door' => 'luna',
            'question' => '¿Qué estoy sintiendo y qué necesito en este momento?',
            'subject' => 'La Luna',
            'name' => 'Ariadna Marin Rodriguez',
            'sign' => 'Libra',
            'degrees' => 14,
            'minutes' => 42,
            'seconds' => 4,
            'house' => 6,
            'ruler_sign' => 'Virgo',
            'ruler_house' => 6,
            'ruler_details' => [['name' => 'Venus']],
            'states' => [
                'harmony' => ['characteristics' => ['H1']],
                'deficit' => ['characteristics' => ['D1']],
                'excess' => ['characteristics' => ['E1']],
            ],
            'previous_doors' => ['sol' => 'Contenido solar'],
            'introduced_rulers' => ['venus' => 'sol'],
        ]);

        $payload = json_decode($prompts['user'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(10, count($payload['bloques_obligatorios']));
        $this->assertNotContains('harmonization', $payload['bloques_obligatorios']);
        $this->assertSame(['H1'], $payload['datos_carta']['caracteristicas_estados']['harmony']);
        $this->assertStringContainsString('Venus ya fue presentado', $payload['continuidad']['instruccion_regente']);
        $this->assertSame(3, $payload['requisitos_de_extension']['shared_intro']['numero_strings']);
        $this->assertSame($payload['bloques_obligatorios'], $payload['salida']['solo_claves']);
    }

    public function test_it_builds_the_ascendant_prompt_without_previous_state_contamination(): void
    {
        $characteristics = [
            'harmony' => ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'H7'],
            'deficit' => ['D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7'],
            'excess' => ['E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'E7'],
        ];
        $prompts = (new PhaseOnePromptBuilder())->build('ascendente', [
            'door' => 'ascendente',
            'question' => '¿Cómo puedo dar este paso de una manera que pueda sostener?',
            'subject' => 'El Ascendente',
            'name' => 'Ariadna Marin Rodriguez',
            'sign' => 'Tauro',
            'degrees' => 3,
            'minutes' => 14,
            'seconds' => 4,
            'house' => 1,
            'ruler_details' => [['name' => 'Venus', 'sign' => 'Virgo', 'house' => 6]],
            'states' => array_map(static fn (array $items): array => ['characteristics' => $items], $characteristics),
            'previous_doors' => ['sol' => ['harmony' => 'estado solar contaminante']],
            'introduced_rulers' => ['venus' => 'sol'],
        ]);

        $payload = json_decode($prompts['user'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(10, $payload['bloques_obligatorios']);
        $this->assertNotContains('harmonization', $payload['bloques_obligatorios']);
        $this->assertSame(7, $payload['requisitos_de_extension']['harmony']['numero_strings']);
        $this->assertSame(7, count($payload['datos_carta']['states']['harmony']['characteristics']));
        $this->assertStringContainsString('eje I–VII', $payload['requisitos_de_extension']['house']['instruccion']);
        $this->assertStringContainsString('Venus ya fue presentado', $payload['continuidad']['instruccion_regente']);
    }

    public function test_it_builds_the_descendant_prompt_with_dual_rulership_and_clean_states(): void
    {
        $characteristics = [
            'harmony' => ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'H7'],
            'deficit' => ['D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7'],
            'excess' => ['E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'E7'],
        ];
        $prompts = (new PhaseOnePromptBuilder())->build('descendente', [
            'door' => 'descendente',
            'question' => '¿Cómo puedo compartir mi vida sin dejar de escucharme?',
            'subject' => 'El Descendente',
            'name' => 'Ariadna Marin Rodriguez',
            'sign' => 'Escorpio',
            'degrees' => 3,
            'minutes' => 14,
            'seconds' => 4,
            'house' => 7,
            'ruler_details' => [['name' => 'Marte'], ['name' => 'Plutón']],
            'states' => array_map(static fn (array $items): array => ['characteristics' => $items], $characteristics),
            'previous_doors' => ['sol' => ['harmony' => 'estado solar contaminante']],
            'introduced_rulers' => ['venus' => 'sol'],
        ]);

        $payload = json_decode($prompts['user'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(10, $payload['bloques_obligatorios']);
        $this->assertSame(['Marte', 'Plutón'], $payload['datos_carta']['rulers']);
        $this->assertSame(7, count($payload['datos_carta']['caracteristicas_estados']['harmony']));
        $this->assertSame(8, $payload['requisitos_de_extension']['ruler']['numero_strings']);
        $this->assertStringContainsString('deseo, petición, acuerdo y norma', $prompts['system']);
        $this->assertStringContainsString('Marte y Plutón', $payload['continuidad']['instruccion_regente']);
    }

    public function test_it_validates_the_json_shape_returned_by_the_provider(): void
    {
        config(['ai.enabled' => true]);
        $minimums = [
            'shared_intro' => 3, 'function' => 3, 'sign' => 4, 'house' => 6, 'ruler' => 6,
            'integration' => 4, 'harmony' => 4, 'deficit' => 4, 'excess' => 4, 'harmonization' => 4, 'closing' => 3,
        ];
        $blocks = array_map(
            static fn (int $minimum): array => array_fill(0, $minimum, 'Párrafo generado'),
            $minimums,
        );
        $generator = new class($blocks) implements AiTextGenerator {
            public function __construct(private array $blocks) {}

            public function generate(string $systemPrompt, string $userPrompt): array
            {
                return $this->blocks;
            }
        };

        $service = new PhaseOneAiContentService($generator, new PhaseOnePromptBuilder());
        $result = $service->generate('sol', ['subject' => 'El Sol']);

        $this->assertSame(array_keys($blocks), array_keys($result));
        $this->assertCount(3, $result['closing']);
        $this->assertSame('Párrafo generado', $result['closing'][0]);
    }

    public function test_it_splits_grouped_narrative_paragraphs_before_validating_minimums(): void
    {
        config(['ai.enabled' => true]);
        $minimums = [
            'shared_intro' => ["Uno\nDos\nTres"], 'function' => ["Uno\nDos\nTres"], 'sign' => ["Uno\nDos\nTres\nCuatro"],
            'house' => ["Uno\nDos\nTres\nCuatro\nCinco\nSeis"], 'ruler' => ["Uno\nDos\nTres\nCuatro\nCinco\nSeis\nSiete\nOcho"],
            'integration' => ["Uno\nDos\nTres\nCuatro"], 'harmony' => ['H1', 'H2', 'H3', 'H4'],
            'deficit' => ['D1', 'D2', 'D3', 'D4'], 'excess' => ['E1', 'E2', 'E3', 'E4'],
            'closing' => ["Uno\nDos\nTres"],
        ];
        $generator = new class($minimums) implements AiTextGenerator {
            public function __construct(private array $blocks) {}
            public function generate(string $systemPrompt, string $userPrompt): array { return $this->blocks; }
        };

        $result = (new PhaseOneAiContentService($generator, new PhaseOnePromptBuilder()))->generate('descendente', ['subject' => 'El Descendente']);

        $this->assertCount(3, $result['shared_intro']);
        $this->assertCount(6, $result['house']);
        $this->assertCount(3, $result['closing']);
    }
}
