<?php

namespace Tests\Unit;

use App\Contracts\StructuredAiTextGenerator;
use App\Services\Doors\DoorPipelineFactory;
use App\Services\Doors\AbstractDoorPipeline;
use App\Services\Doors\SolPipeline;
use App\Services\PhaseOneAiContentService;
use App\Services\PhaseOnePromptBuilder;
use App\Services\SunAstrologicalFactValidator;
use App\Services\OpenAiTextGenerator;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class SunGenerationPipelineTest extends TestCase
{
    public function test_solar_generation_uses_small_structured_calls_and_places_content_under_each_heading(): void
    {
        config(['ai.enabled' => true]);
        $generator = new class implements StructuredAiTextGenerator {
            public array $stages = [];

            public function generate(string $systemPrompt, string $userPrompt, array $meta = []): array
            {
                throw new RuntimeException('The solar pilot must request a schema.');
            }

            public function generateStructured(string $systemPrompt, string $userPrompt, array $schema, array $meta = []): array
            {
                $stage = json_decode($userPrompt, true, 512, JSON_THROW_ON_ERROR)['stage'];
                $this->stages[] = $stage;
                $this->assertSchema($schema);
                return SunGenerationPipelineTest::sample($stage) + ['_usage' => ['input_tokens' => 10, 'output_tokens' => 20, 'total_tokens' => 30]];
            }

            private function assertSchema(array $schema): void
            {
                if (($schema['additionalProperties'] ?? null) !== false) {
                    throw new RuntimeException('Schema must be strict.');
                }
            }
        };

        $service = new PhaseOneAiContentService($generator, new PhaseOnePromptBuilder());
        $blocks = $service->generate('sol', self::context());

        $this->assertSame(AbstractDoorPipeline::STAGES, $generator->stages);
        $this->assertSame(count(AbstractDoorPipeline::STAGES) * 30, $service->usage()['total_tokens']);
        $this->assertCount(11, $blocks);
        $harmony = implode('', $blocks['harmony']);
        $this->assertLessThan(strpos($harmony, '<h3>Características'), strpos($harmony, '<p>'));
        $this->assertStringContainsString('</h3><ol><li>', $harmony);
        $this->assertStringContainsString('<h3>Preguntas de autoobservación</h3>', implode('', $blocks['closing']));
    }

    public function test_it_rejects_wrong_sign_and_a_shared_position_for_two_planets(): void
    {
        $validator = new SunAstrologicalFactValidator();
        foreach (['Venus en Virgo, casa VI.', 'Marte y Plutón en Capricornio en casa IX.', 'Marte en Capricornio y Plutón en Capricornio.', 'Venus a 18° de Escorpio.'] as $text) {
            try {
                $validator->validate(['text' => $text], self::context()['astrological_facts']);
                $this->fail("A contradictory position was accepted: {$text}");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('contradictoria', $exception->getMessage());
            }
        }
    }

    public function test_it_rejects_a_state_without_matching_guidelines_and_examples(): void
    {
        $state = self::sample('harmony_examples_2');
        array_pop($state['harmony']['examples']);
        $this->expectException(RuntimeException::class);
        (new SolPipeline())->validate('harmony_examples_2', $state, self::context());
    }

    public function test_it_rejects_a_verbatim_generic_phrase_reused_across_doors(): void
    {
        $harmonization = self::sample('harmonization')['harmonization'];
        $harmonization['equilibrium']['paragraphs'][0] .= ' El punto de equilibrio permite elegir cuándo utilizar el recurso y cuándo detenerlo.';
        $result = ['harmonization' => $harmonization];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/frase genérica/');
        (new \App\Services\Doors\LunaPipeline())->validate('harmonization', $result, self::context('luna'));
    }

    /** @return list<array{0: string}> */
    public static function nonSolDoorProvider(): array
    {
        return [['luna'], ['ascendente'], ['descendente']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonSolDoorProvider')]
    public function test_non_sol_doors_get_door_specific_instructions_instead_of_a_generic_template(string $door): void
    {
        $pipeline = DoorPipelineFactory::for($door);
        $prompt = $pipeline->buildPrompt('function', self::context($door), []);
        $payload = json_decode($prompt['user'], true, 512, JSON_THROW_ON_ERROR);

        $genericTemplate = sprintf('función psicológica de %s, diferencia con las otras puertas', $pipeline->label());
        $this->assertStringNotContainsString($genericTemplate, $payload['required_output']['function']);
    }

    public function test_sol_prompts_are_unchanged_by_the_shared_pipeline_refactor(): void
    {
        $prompt = (new SolPipeline())->buildPrompt('function', self::context(), []);
        $payload = json_decode($prompt['user'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            '3 párrafos de al menos 70 palabras: función psicológica de el Sol, diferencia con las otras puertas y posición particular.',
            $payload['required_output']['function'],
        );
    }

    public function test_openai_request_uses_strict_json_schema_for_the_solar_pilot(): void
    {
        config(['ai.api_key' => 'test-key', 'ai.base_url' => 'https://api.openai.com/v1']);
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"harmony":{}}']]],
            'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 3, 'total_tokens' => 5],
        ])]);

        $result = (new OpenAiTextGenerator())->generateStructured('system', 'user', (new SolPipeline())->schemaForStage('harmony_development'));

        $this->assertSame(5, $result['_usage']['total_tokens']);
        Http::assertSent(static fn ($request): bool =>
            $request['response_format']['type'] === 'json_schema'
            && $request['response_format']['json_schema']['strict'] === true
            && $request['response_format']['json_schema']['schema']['additionalProperties'] === false
        );
    }

    public function test_solar_generation_retries_an_invalid_state_before_assembling(): void
    {
        config(['ai.enabled' => true]);
        $generator = new class implements StructuredAiTextGenerator {
            public int $calls = 0;
            private bool $failedHarmony = false;

            public function generate(string $systemPrompt, string $userPrompt, array $meta = []): array
            {
                throw new RuntimeException('Unexpected legacy call.');
            }

            public function generateStructured(string $systemPrompt, string $userPrompt, array $schema, array $meta = []): array
            {
                $this->calls++;
                $stage = json_decode($userPrompt, true, 512, JSON_THROW_ON_ERROR)['stage'];
                $result = SunGenerationPipelineTest::sample($stage);
                if ($stage === 'harmony_examples_2' && ! $this->failedHarmony) {
                    $this->failedHarmony = true;
                    array_pop($result['harmony']['examples']);
                }
                return $result;
            }
        };

        $blocks = (new PhaseOneAiContentService($generator, new PhaseOnePromptBuilder()))->generate('sol', self::context());
        $this->assertSame(count(AbstractDoorPipeline::STAGES) + 1, $generator->calls);
        $this->assertCount(11, $blocks);
    }

    public static function sample(string $stage, string $door = 'sol'): array
    {
        $rulerCounts = ['sol' => 6, 'luna' => 6, 'ascendente' => 6, 'descendente' => 8];
        $paragraph = trim(str_repeat('Una decisión concreta permite observar la necesidad solar y revisar su resultado con tiempo y cuidado. ', 8));
        $example = trim(str_repeat('En una situación cotidiana, una persona observa su reacción, nombra su necesidad, responde con claridad y aprende del resultado. ', 5));
        $state = [
            'development' => array_fill(0, 4, $paragraph),
            'characteristics' => array_map(static fn (int $id): array => ['id' => $id, 'text' => "Preferencia propia observable número {$id}"], range(1, 7)),
            'guidelines' => array_map(static fn (int $id): array => ['id' => $id, 'text' => $paragraph], range(1, 7)),
            'examples' => array_map(static fn (int $id): array => ['id' => $id, 'text' => $example], range(1, 7)),
        ];
        $closing = [
            'question_intro' => [$paragraph],
            'questions' => array_fill(0, 5, '¿Qué preferencia propia puedo expresar en esta decisión?'),
            'central_phrase' => 'Puedo elegir con claridad y revisar después.',
            'support_phrases' => array_fill_keys(['to_begin', 'to_restore_measure', 'to_review', 'to_integrate'], 'Puedo observar una decisión y ajustar con calma.'),
        ];

        return match ($stage) {
            'function' => ['shared_intro' => ['paragraphs' => array_fill(0, 3, $paragraph)], 'function' => ['paragraphs' => array_fill(0, 3, $paragraph)]],
            'sign', 'house', 'integration' => [$stage => ['paragraphs' => array_fill(0, ['sign' => 4, 'house' => 6, 'integration' => 4][$stage], $paragraph)]],
            'ruler' => ['ruler' => ['paragraphs' => array_fill(0, $rulerCounts[$door] ?? 6, $paragraph)]],
            'harmony_development', 'deficit_development', 'excess_development' => [strtok($stage, '_') => ['development' => $state['development']]],
            'harmony_characteristics', 'deficit_characteristics', 'excess_characteristics' => [strtok($stage, '_') => ['characteristics' => $state['characteristics']]],
            'harmony_guidelines', 'deficit_guidelines', 'excess_guidelines' => [strtok($stage, '_') => ['guidelines' => $state['guidelines']]],
            'harmony_examples_1', 'deficit_examples_1', 'excess_examples_1' => [strtok($stage, '_') => ['examples' => array_slice($state['examples'], 0, 3)]],
            'harmony_examples_2', 'deficit_examples_2', 'excess_examples_2' => [strtok($stage, '_') => ['examples' => array_slice($state['examples'], 3)]],
            'harmonization' => [
                'harmonization' => [
                    'from_deficit' => ['paragraphs' => [$paragraph, $paragraph], 'points' => array_fill(0, 3, 'Observa una decisión propia y comprueba qué cambia después.')],
                    'from_excess' => ['paragraphs' => [$paragraph, $paragraph], 'points' => array_fill(0, 3, 'Observa una decisión propia y comprueba qué cambia después.')],
                    'equilibrium' => ['paragraphs' => [$paragraph, $paragraph], 'references' => array_fill(0, 4, 'Una preferencia expresada y revisada con claridad.')],
                ],
            ],
            'closing' => ['closing' => $closing],
        };
    }

    /** @return list<array{0: string}> */
    public static function doorProvider(): array
    {
        return [['sol'], ['luna'], ['ascendente'], ['descendente']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('doorProvider')]
    public function test_each_door_uses_its_own_independent_pipeline_class(string $door): void
    {
        $pipeline = DoorPipelineFactory::for($door);
        $this->assertSame($door, $pipeline->door());
        $this->assertSame($door === 'descendente' ? 8 : 6, $pipeline->rulerParagraphCount());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('doorProvider')]
    public function test_generation_works_for_every_door_with_correct_stage_count_and_shapes(string $door): void
    {
        config(['ai.enabled' => true]);
        $generator = new class implements StructuredAiTextGenerator {
            public array $stages = [];
            public array $doors = [];

            public function generate(string $systemPrompt, string $userPrompt, array $meta = []): array
            {
                throw new RuntimeException('This pipeline must request a schema.');
            }

            public function generateStructured(string $systemPrompt, string $userPrompt, array $schema, array $meta = []): array
            {
                $decoded = json_decode($userPrompt, true, 512, JSON_THROW_ON_ERROR);
                $stage = $decoded['stage'];
                $door = $decoded['door'];
                $this->stages[] = $stage;
                $this->doors[] = $door;

                return SunGenerationPipelineTest::sample($stage, $door) + ['_usage' => ['input_tokens' => 10, 'output_tokens' => 20, 'total_tokens' => 30]];
            }
        };

        $blocks = (new PhaseOneAiContentService($generator, new PhaseOnePromptBuilder()))->generate($door, self::context($door));

        $this->assertSame(AbstractDoorPipeline::STAGES, $generator->stages);
        $this->assertSame(array_fill(0, count(AbstractDoorPipeline::STAGES), $door), $generator->doors);
        $this->assertCount(11, $blocks);
        $this->assertArrayHasKey('harmonization', $blocks);
        $this->assertStringContainsString('<h3>Preguntas de autoobservación</h3>', implode('', $blocks['closing']));
    }

    private static function context(string $door = 'sol'): array
    {
        $facts = [];
        foreach (['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto', 'ascendant', 'descendant'] as $key) {
            $facts[$key] = ['sign' => 'Libra', 'house' => 6, 'degrees' => 12, 'minutes' => 0, 'seconds' => 0];
        }
        $facts['venus']['sign'] = 'Escorpio';
        $facts['mars'] = ['sign' => 'Capricornio', 'house' => 9, 'degrees' => 8, 'minutes' => 0, 'seconds' => 0];
        $facts['pluto']['sign'] = 'Escorpio';
        $facts['rulers'] = ['traditional' => ['venus'], 'modern' => ['venus']];
        return ['door' => $door, 'name' => 'María', 'question' => '¿Qué quiero aportar y elegir?', 'astrological_facts' => $facts];
    }
}
