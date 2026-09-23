<?php

namespace App\Services;

final class SunResponseSchema
{
    public function forStage(string $stage): array
    {
        $paragraphs = $this->object(['paragraphs' => $this->strings()]);
        $item = $this->object(['id' => ['type' => 'integer'], 'text' => ['type' => 'string']]);
        $state = $this->object([
            'development' => $this->strings(),
            'characteristics' => ['type' => 'array', 'items' => $item],
            'guidelines' => ['type' => 'array', 'items' => $item],
            'examples' => ['type' => 'array', 'items' => $item],
        ]);

        return match ($stage) {
            'function' => $this->object(['shared_intro' => $paragraphs, 'function' => $paragraphs]),
            'sign', 'house', 'ruler', 'integration' => $this->object([$stage => $paragraphs]),
            'harmony', 'deficit', 'excess' => $this->object([$stage => $state]),
            'final' => $this->object([
                'harmonization' => $this->object([
                    'from_deficit' => $this->object(['paragraphs' => $this->strings(), 'points' => $this->strings()]),
                    'from_excess' => $this->object(['paragraphs' => $this->strings(), 'points' => $this->strings()]),
                    'equilibrium' => $this->object(['paragraphs' => $this->strings(), 'references' => $this->strings()]),
                ]),
                'closing' => $this->object([
                    'question_intro' => $this->strings(), 'questions' => $this->strings(),
                    'central_phrase' => ['type' => 'string'],
                    'support_phrases' => $this->object(array_fill_keys(
                        ['to_begin', 'to_restore_measure', 'to_review', 'to_integrate'], ['type' => 'string'],
                    )),
                ]),
            ]),
            default => throw new \InvalidArgumentException("Etapa solar no válida: {$stage}"),
        };
    }

    private function strings(): array
    {
        return ['type' => 'array', 'items' => ['type' => 'string']];
    }

    private function object(array $properties): array
    {
        return [
            'type' => 'object', 'properties' => $properties,
            'required' => array_keys($properties), 'additionalProperties' => false,
        ];
    }
}
