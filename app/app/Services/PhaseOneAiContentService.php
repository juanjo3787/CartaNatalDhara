<?php

namespace App\Services;

use App\Contracts\AiTextGenerator;
use RuntimeException;

final class PhaseOneAiContentService
{
    /** @var array{input_tokens: int, output_tokens: int, total_tokens: int} */
    private array $lastUsage = ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0];
    /** @var array{system: string, user: string} */
    private array $lastPrompts = ['system' => '', 'user' => ''];

    private const MINIMUM_PARAGRAPHS = [
        'shared_intro' => 3,
        'function' => 3,
        'sign' => 4,
        'house' => 6,
        'ruler' => 6,
        'integration' => 4,
        'harmony' => 4,
        'deficit' => 4,
        'excess' => 4,
        'closing' => 3,
    ];

    public function __construct(
        private readonly AiTextGenerator $generator,
        private readonly PhaseOnePromptBuilder $promptBuilder,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, list<string>>
     */
    public function generate(string $door, array $context): array
    {
        if (! config('ai.enabled')) {
            throw new RuntimeException('La generación de IA está desactivada. Configura AI_ENABLED=true.');
        }

        $prompts = $this->promptBuilder->build($door, $context);
        $this->lastPrompts = $prompts;
        $result = $this->generator->generate($prompts['system'], $prompts['user']);
        $usage = $result['_usage'] ?? [];
        $this->lastUsage = [
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
        ];

        foreach ($this->promptBuilder->blocks() as $block) {
            if (! isset($result[$block]) || ! is_array($result[$block])) {
                throw new RuntimeException("La respuesta de IA no contiene el bloque obligatorio: {$block}");
            }

            $result[$block] = array_values(array_filter(
                $result[$block],
                static fn (mixed $paragraph): bool => is_string($paragraph) && trim($paragraph) !== '',
            ));

            if ($result[$block] === []) {
                throw new RuntimeException("El bloque de IA está vacío: {$block}");
            }

            $minimum = self::MINIMUM_PARAGRAPHS[$block] ?? 1;
            if (count($result[$block]) < $minimum) {
                throw new RuntimeException("El bloque de IA {$block} debe contener al menos {$minimum} párrafos.");
            }
        }

        return array_intersect_key($result, array_flip($this->promptBuilder->blocks()));
    }

    /** @return array{input_tokens: int, output_tokens: int, total_tokens: int} */
    public function usage(): array
    {
        return $this->lastUsage;
    }

    /** @return array{system: string, user: string} */
    public function prompts(): array
    {
        return $this->lastPrompts;
    }
}
