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
                $characteristics = $context['states'][$block]['characteristics']
                    ?? $context['caracteristicas_estados'][$block]
                    ?? [];

                if (is_array($characteristics) && $characteristics !== []) {
                    $expectedCount = count($characteristics);
                    $actualCount = count($result[$block]);
                    
                    // Permitimos una tolerancia de ±2 strings para dar flexibilidad a la IA
                    $minAllowed = max(1, $expectedCount - 2);
                    $maxAllowed = $expectedCount + 2;
                    
                    if ($actualCount < $minAllowed || $actualCount > $maxAllowed) {
                        throw new RuntimeException(sprintf(
                            'El bloque de IA %s debe contener entre %d y %d strings (se esperaban %d características). Se recibieron %d strings.',
                            $block,
                            $minAllowed,
                            $maxAllowed,
                            $expectedCount,
                            $actualCount,
                        ));
                    }
                }

                // Validar estructura de 4 capas: verificar que existen las cabeceras requeridas
                $requiredHeaders = [
                    'harmony' => ['Características que puedes observar', 'Pautas y consideraciones para reconocer este equilibrio', 'Ejemplos cotidianos de estas pautas'],
                    'deficit' => ['Características que puedes observar', 'Pautas y consideraciones para empezar a armonizar', 'Ejemplos cotidianos y formas de empezar a armonizar'],
                    'excess' => ['Características que puedes observar', 'Pautas y consideraciones para recuperar una medida adecuada', 'Ejemplos cotidianos y formas de recuperar medida'],
                ];

                $blockHeaders = $requiredHeaders[$block] ?? [];
                $content = implode(' ', $result[$block]);
                
                foreach ($blockHeaders as $header) {
                    if (!str_contains($content, $header)) {
                        throw new RuntimeException("El bloque de IA {$block} debe contener la cabecera '{$header}'. Estructura de 4 capas incompleta.");
                    }
                }

                // Validar profundidad: verificar que existe contenido antes de las cabeceras (desarrollo interpretativo)
                $firstHeader = $blockHeaders[0] ?? '';
                if ($firstHeader && str_contains($content, $firstHeader)) {
                    $contentBeforeHeader = explode($firstHeader, $content)[0] ?? '';
                    $wordCountBefore = str_word_count(strip_tags($contentBeforeHeader));
                    if ($wordCount < 30) {
                        throw new RuntimeException("El bloque de IA {$block} debe tener un DESARROLLO INTERPRETATIVO AMPLIO (mínimo 30 palabras) antes de la primera cabecera '{$firstHeader}'. Solo tiene {$wordCount} palabras.");
                    }
                }

                // Validar profundidad de ejemplos: verificar longitud de strings después de la cabecera de ejemplos
                $exampleHeader = $blockHeaders[2] ?? '';
                if ($exampleHeader && str_contains($content, $exampleHeader)) {
                    $contentAfterExamples = explode($exampleHeader, $content)[1] ?? '';
                    // Verificar que los ejemplos tengan suficiente longitud
                    $shortExamples = array_filter($result[$block], function($string) use ($exampleHeader) {
                        return str_contains($string, $exampleHeader) || str_word_count(strip_tags($string)) < 40;
                    });
                    
                    if (count($shortExamples) > 0) {
                        throw new RuntimeException("El bloque de IA {$block} contiene ejemplos demasiado cortos. Cada ejemplo debe tener al menos 40 palabras para ser una mini escena narrativa con contexto, respuesta, experiencia, alternativa y aprendizaje.");
                    }
                }
            }
        }

        return array_intersect_key($result, array_flip($this->promptBuilder->blocks($door)));
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
