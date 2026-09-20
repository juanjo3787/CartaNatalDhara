<?php

namespace App\Contracts;

interface AiTextGenerator
{
    /**
     * @return array<string, mixed>
     */
    public function generate(string $systemPrompt, string $userPrompt): array;
}
