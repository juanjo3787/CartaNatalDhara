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
        $this->assertStringContainsString('INSTRUCCIONES_CONTINUIDAD_DESCENDENTE_FASE_1.pdf', $prompts['system']);
        $this->assertStringContainsString('descendente', strtolower($prompts['user']));
        $this->assertStringContainsString('Venus ya explicado', $prompts['user']);
        $this->assertStringContainsString('regentes_ya_presentados', $prompts['user']);
        $this->assertSame(10, count($builder->blocks()));
    }

    public function test_it_validates_the_json_shape_returned_by_the_provider(): void
    {
        config(['ai.enabled' => true]);
        $minimums = [
            'shared_intro' => 3, 'function' => 3, 'sign' => 4, 'house' => 6, 'ruler' => 6,
            'integration' => 4, 'harmony' => 4, 'deficit' => 4, 'excess' => 4, 'closing' => 3,
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
}
