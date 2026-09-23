<?php

namespace App\Contracts;

interface StructuredAiTextGenerator extends AiTextGenerator
{
    /** @param array<string, mixed> $meta Optional logging context (door, stage, attempt, chart_id). */
    public function generateStructured(string $systemPrompt, string $userPrompt, array $schema, array $meta = []): array;
}
