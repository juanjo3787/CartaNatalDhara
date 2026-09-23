<?php

namespace App\Services;

use App\Contracts\AiTextGenerator;
use App\Contracts\StructuredAiTextGenerator;
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
        'harmony' => 4, // Mínimo base, pero debe tener desarrollo interpretativo + características + pautas + ejemplos
        'deficit' => 4, // Mínimo base, pero debe tener desarrollo interpretativo + características + pautas + ejemplos
        'excess' => 4, // Mínimo base, pero debe tener desarrollo interpretativo + características + pautas + ejemplos
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

        if ($door === 'sol' && isset($context['astrological_facts'])) {
            return $this->generateSun($context);
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

        foreach ($this->promptBuilder->blocks($door) as $block) {
            if (! isset($result[$block]) || ! is_array($result[$block])) {
                throw new RuntimeException("La respuesta de IA no contiene el bloque obligatorio: {$block}");
            }

            $result[$block] = array_values(array_filter(
                $result[$block],
                static fn (mixed $paragraph): bool => is_string($paragraph) && trim($paragraph) !== '',
            ));

            if (! in_array($block, ['harmony', 'deficit', 'excess'], true)) {
                $result[$block] = array_values(array_filter(
                    array_merge(...array_map(
                        static fn (string $paragraph): array => preg_split('/\R+|(?<=<\/p>)\s*(?=<p>)/i', trim($paragraph)) ?: [$paragraph],
                        $result[$block],
                    )),
                    static fn (string $paragraph): bool => trim($paragraph) !== '',
                ));
            }

            if ($result[$block] === []) {
                throw new RuntimeException("El bloque de IA está vacío: {$block}");
            }

            $minimum = self::MINIMUM_PARAGRAPHS[$block] ?? 1;
            if (count($result[$block]) < $minimum) {
                throw new RuntimeException("El bloque de IA {$block} debe contener al menos {$minimum} párrafos.");
            }

            $expectedCount = $this->expectedCount($door, $block, $context);
            if ($expectedCount !== null) {
                $actualCount = count($result[$block]);
                // Permitimos una tolerancia de ±2 strings para bloques de estados
                if (in_array($block, ['harmony', 'deficit', 'excess'], true)) {
                    $minAllowed = max(1, $expectedCount - 2);
                    $maxAllowed = $expectedCount + 2;
                    if ($actualCount < $minAllowed || $actualCount > $maxAllowed) {
                        throw new RuntimeException("El bloque de IA {$block} debe contener entre {$minAllowed} y {$maxAllowed} strings (se esperaban {$expectedCount}). Se recibieron {$actualCount}.");
                    }
                } elseif ($actualCount !== $expectedCount) {
                    throw new RuntimeException("El bloque de IA {$block} debe contener exactamente {$expectedCount} strings independientes.");
                }
            }

            if (in_array($block, ['harmony', 'deficit', 'excess'], true)) {
                // Validación más flexible de estructura: verificar cabeceras pero permitir variaciones en formato
                $requiredHeaders = [
                    'harmony' => ['Características que puedes observar', 'Pautas y consideraciones para reconocer este equilibrio', 'Ejemplos cotidianos de estas pautas'],
                    'deficit' => ['Características que puedes observar', 'Pautas y consideraciones para empezar a armonizar', 'Ejemplos cotidianos y formas de empezar a armonizar'],
                    'excess' => ['Características que puedes observar', 'Pautas y consideraciones para recuperar una medida adecuada', 'Ejemplos cotidianos y formas de recuperar medida'],
                ];

                $blockHeaders = $requiredHeaders[$block] ?? [];
                $content = implode(' ', $result[$block]);
                
                $missingHeaders = [];
                foreach ($blockHeaders as $header) {
                    if (!str_contains($content, $header)) {
                        $missingHeaders[] = $header;
                    }
                }

                if (!empty($missingHeaders)) {
                    throw new RuntimeException("El bloque de IA {$block} debe contener las cabeceras: " . implode(', ', $missingHeaders) . ". Estructura de 4 capas incompleta.");
                }

                // Validación de profundidad más flexible: verificar que existe contenido sustancial
                $firstHeader = $blockHeaders[0] ?? '';
                if ($firstHeader && str_contains($content, $firstHeader)) {
                    $contentBeforeHeader = explode($firstHeader, $content)[0] ?? '';
                    $wordCountBefore = str_word_count(strip_tags($contentBeforeHeader));
                    if ($wordCount < 15) {
                        throw new RuntimeException("El bloque de IA {$block} debe tener desarrollo interpretativo antes de la primera cabecera '{$firstHeader}'. Solo tiene {$wordCount} palabras.");
                    }
                }
            }
        }

        return array_intersect_key($result, array_flip($this->promptBuilder->blocks($door)));
    }

    private function generateSun(array $context): array
    {
        $completed = [];
        $promptLog = [];
        $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0];

        foreach (SunPromptBuilder::STAGES as $stage) {
            $valid = $this->generateSunStage($stage, $context, $completed);
            $completed = array_merge($completed, $valid);
            foreach (array_keys($usage) as $key) {
                $usage[$key] += $this->lastUsage[$key];
            }
            $promptLog[] = ['stage' => $stage, ...$this->lastPrompts];
        }

        $this->lastUsage = $usage;
        $this->lastPrompts = [
            'system' => implode("\n\n", array_map(static fn (array $prompt): string => "[{$prompt['stage']}]\n{$prompt['system']}", $promptLog)),
            'user' => implode("\n\n", array_map(static fn (array $prompt): string => "[{$prompt['stage']}]\n{$prompt['user']}", $promptLog)),
        ];

        return (new SunContentRenderer())->render($completed);
    }

    public function generateSunStage(string $stage, array $context, array $completed = []): array
    {
        if (! config('ai.enabled')) {
            throw new RuntimeException('La generación de IA está desactivada.');
        }
        $prompts = (new SunPromptBuilder())->build($stage, $context, $completed);
        $this->lastUsage = ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0];
        $promptLog = [];
        $lastError = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $userPrompt = $prompts['user'];
            if ($lastError !== null) {
                $repair = json_decode($userPrompt, true, 512, JSON_THROW_ON_ERROR);
                $repair['validation_feedback'] = "La respuesta anterior no pasó la validación: {$lastError}. Devuelve de nuevo el bloque completo con la estructura y profundidad solicitadas.";
                $userPrompt = json_encode($repair, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
            $promptLog[] = ['system' => $prompts['system'], 'user' => $userPrompt];
            $result = $this->generator instanceof StructuredAiTextGenerator
                ? $this->generator->generateStructured($prompts['system'], $userPrompt, (new SunResponseSchema())->forStage($stage))
                : $this->generator->generate($prompts['system'], $userPrompt);
            foreach (array_keys($this->lastUsage) as $key) {
                $this->lastUsage[$key] += (int) ($result['_usage'][$key] ?? 0);
            }
            try {
                $valid = (new SunContentValidator())->validate($stage, $result, $context);
                $lastError = null;
                break;
            } catch (RuntimeException $exception) {
                $lastError = $exception->getMessage();
            }
        }
        $this->lastPrompts = [
            'system' => implode("\n\n", array_column($promptLog, 'system')),
            'user' => implode("\n\n", array_column($promptLog, 'user')),
        ];
        if ($lastError !== null) {
            throw new RuntimeException("La etapa solar {$stage} no superó la validación: {$lastError}");
        }
        return $valid;
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

    /** @param array<string, mixed> $context */
    private function expectedCount(string $door, string $block, array $context): ?int
    {
        if (in_array($block, ['harmony', 'deficit', 'excess'], true)) {
            $characteristics = $context['states'][$block]['characteristics']
                ?? $context['caracteristicas_estados'][$block]
                ?? [];

            if (is_array($characteristics) && $characteristics !== []) {
                return count($characteristics);
            }
        }

        return null;
    }
}
