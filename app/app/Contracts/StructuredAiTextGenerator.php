<?php

namespace App\Contracts;

interface StructuredAiTextGenerator extends AiTextGenerator
{
    public function generateStructured(string $systemPrompt, string $userPrompt, array $schema): array;
}
