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
        ]);

        $this->assertStringContainsString('deseo, petición, acuerdo y norma', $prompts['system']);
        $this->assertStringContainsString('descendente', strtolower($prompts['user']));
        $this->assertSame(10, count($builder->blocks()));
    }

    public function test_it_validates_the_json_shape_returned_by_the_provider(): void
    {
        config(['ai.enabled' => true]);
        $blocks = array_fill_keys((new PhaseOnePromptBuilder())->blocks(), ['Párrafo generado']);
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
        $this->assertSame(['Párrafo generado'], $result['closing']);
    }
}
