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

        $http = Http::baseUrl((string) config('ai.base_url'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('ai.timeout', 25))
            ->connectTimeout((int) config('ai.connect_timeout', 10));

        if (! config('ai.verify_ssl', true)) {
            $http->withoutVerifying();
        }

        $response = $http->post('/chat/completions', [
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

        $decoded['_usage'] = [
            'input_tokens' => (int) $response->json('usage.prompt_tokens', 0),
            'output_tokens' => (int) $response->json('usage.completion_tokens', 0),
            'total_tokens' => (int) $response->json('usage.total_tokens', 0),
        ];

        return $decoded;
    }
}
