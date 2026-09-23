<?php

namespace App\Contracts;

interface AiTextGenerator
{
    /**
     * @param array<string, mixed> $meta Optional logging context (door, stage, attempt, chart_id).
     * @return array<string, mixed>
     */
    public function generate(string $systemPrompt, string $userPrompt, array $meta = []): array;
}
