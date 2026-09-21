<?php

namespace App\Services;

use App\Contracts\AiTextGenerator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpenAiTextGenerator implements AiTextGenerator
{
    public function generate(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = (string) config('ai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('AI_API_KEY no está configurada.');
        }

        $response = Http::baseUrl((string) config('ai.base_url'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('ai.timeout', 90))
            ->post('/chat/completions', [
                'model' => config('ai.model'),
                'temperature' => config('ai.temperature', 0.7),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'ChatGPT respondió con HTTP %d: %s',
                $response->status(),
                (string) $response->json('error.message', 'Error sin detalle'),
            ));
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('La API de IA no devolvió contenido textual.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('La respuesta de IA no contiene JSON válido.');
        }

        return $decoded;
    }
}
