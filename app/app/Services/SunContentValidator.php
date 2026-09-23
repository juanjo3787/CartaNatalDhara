<?php

namespace App\Services;

use RuntimeException;

final class SunContentValidator
{
    private const FOUNDATION_COUNTS = [
        'shared_intro' => 3, 'function' => 3, 'sign' => 4,
        'house' => 6, 'ruler' => 6, 'integration' => 4,
    ];

    public function validate(string $stage, array $result, array $context): array
    {
        unset($result['_usage']);
        if (in_array($stage, ['function', 'sign', 'house', 'ruler', 'integration'], true)) {
            foreach (self::FOUNDATION_COUNTS as $key => $count) {
                if ($key !== $stage && ! ($stage === 'function' && $key === 'shared_intro')) {
                    continue;
                }
                $minimumWords = match ($key) {
                    'shared_intro' => 20,
                    'house', 'ruler' => 80,
                    default => 70,
                };
                $this->paragraphs($result[$key]['paragraphs'] ?? null, $count, $count, $minimumWords, $key);
            }
        } elseif (in_array($stage, ['harmony', 'deficit', 'excess'], true)) {
            $state = $result[$stage] ?? null;
            if (! is_array($state)) {
                throw new RuntimeException("Falta el estado solar {$stage}.");
            }
            $this->paragraphs($state['development'] ?? null, 4, 6, 50, "{$stage}.development");
            $count = 7;
            foreach (['characteristics' => 3, 'guidelines' => 40, 'examples' => 80] as $key => $minimumWords) {
                $items = $state[$key] ?? null;
                if (! is_array($items) || count($items) !== $count) {
                    throw new RuntimeException("{$stage}.{$key} debe contener {$count} elementos.");
                }
                foreach (array_values($items) as $index => $item) {
                    if (! is_array($item) || ($item['id'] ?? null) !== $index + 1) {
                        throw new RuntimeException("ID incorrecto en {$stage}.{$key}.");
                    }
                    $this->plainText($item['text'] ?? null, $minimumWords, "{$stage}.{$key}.".($index + 1));
                    if ($key === 'characteristics' && $this->words($item['text']) > 30) {
                        throw new RuntimeException("La característica {$index} de {$stage} debe ser breve.");
                    }
                }
            }
        } elseif ($stage === 'final') {
            $harmonization = $result['harmonization'] ?? [];
            foreach (['from_deficit', 'from_excess'] as $key) {
                $this->paragraphs($harmonization[$key]['paragraphs'] ?? null, 2, 4, 45, $key);
                $this->paragraphs($harmonization[$key]['points'] ?? null, 3, 3, 8, "{$key}.points");
            }
            $this->paragraphs($harmonization['equilibrium']['paragraphs'] ?? null, 2, 4, 45, 'equilibrium');
            $this->paragraphs($harmonization['equilibrium']['references'] ?? null, 4, 4, 6, 'references');
            $closing = $result['closing'] ?? [];
            $this->paragraphs($closing['question_intro'] ?? null, 1, 2, 25, 'question_intro');
            $this->paragraphs($closing['questions'] ?? null, 5, 5, 7, 'questions');
            foreach ($closing['questions'] as $question) {
                if (! str_contains($question, '?')) {
                    throw new RuntimeException('Las preguntas solares deben formularse como preguntas.');
                }
            }
            $this->plainText($closing['central_phrase'] ?? null, 5, 'central_phrase');
            foreach (['to_begin', 'to_restore_measure', 'to_review', 'to_integrate'] as $key) {
                $this->plainText($closing['support_phrases'][$key] ?? null, 5, "support_phrases.{$key}");
            }
        } else {
            throw new RuntimeException("Etapa solar desconocida: {$stage}");
        }

        (new SunAstrologicalFactValidator())->validate($result, $context['astrological_facts']);
        return $result;
    }

    private function paragraphs(mixed $items, int $minimum, int $maximum, int $minimumWords, string $path): void
    {
        if (! is_array($items) || count($items) < $minimum || count($items) > $maximum) {
            throw new RuntimeException("{$path} debe contener entre {$minimum} y {$maximum} textos.");
        }
        foreach ($items as $index => $item) {
            $this->plainText($item, $minimumWords, "{$path}.{$index}");
        }
    }

    private function plainText(mixed $text, int $minimumWords, string $path): void
    {
        if (! is_string($text) || trim($text) === '' || preg_match('/<[^>]*>|https?:\/\/|\*\*/iu', $text)) {
            throw new RuntimeException("Texto inválido en {$path}.");
        }
        if ($this->words($text) < $minimumWords) {
            throw new RuntimeException("{$path} necesita al menos {$minimumWords} palabras desarrolladas.");
        }
    }

    private function words(string $text): int
    {
        return preg_match_all('/[\p{L}\p{N}]+/u', $text);
    }
}
