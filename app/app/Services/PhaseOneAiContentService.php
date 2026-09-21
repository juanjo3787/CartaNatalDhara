<?php

namespace App\Services;

use App\Contracts\AiTextGenerator;
use RuntimeException;

final class PhaseOneAiContentService
{
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
        $result = $this->generator->generate($prompts['system'], $prompts['user']);

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
        }

        return array_intersect_key($result, array_flip($this->promptBuilder->blocks()));
    }
}
