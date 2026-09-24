<?php

namespace App\Services;

use App\Contracts\StructuredAiTextGenerator;
use App\Exceptions\AiGenerationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OpenAiTextGenerator implements StructuredAiTextGenerator
{
    public function generate(string $systemPrompt, string $userPrompt, array $meta = []): array
    {
        return $this->generateWithFormat($systemPrompt, $userPrompt, ['type' => 'json_object'], $meta);
    }

    public function generateStructured(string $systemPrompt, string $userPrompt, array $schema, array $meta = []): array
    {
        return $this->generateWithFormat($systemPrompt, $userPrompt, [
            'type' => 'json_schema',
            'json_schema' => ['name' => 'phase1_block', 'strict' => true, 'schema' => $schema],
        ], $meta);
    }

    /** @param array<string, mixed> $meta */
    private function generateWithFormat(string $systemPrompt, string $userPrompt, array $responseFormat, array $meta = []): array
    {
        $apiKey = (string) config('ai.api_key');

        if ($apiKey === '') {
            throw new AiGenerationException(AiGenerationException::UNKNOWN_ERROR, 'AI_API_KEY no está configurada.');
        }

        $isStructured = $responseFormat['type'] === 'json_schema';
        // A stage must fail cleanly before Cloudflare closes the request at 120 seconds.
        $timeout = (int) ($isStructured ? config('ai.stage_timeout', 95) : config('ai.timeout', 90));
        $maxTokens = (int) ($isStructured ? config('ai.sun_max_completion_tokens', 6000) : config('ai.door_max_completion_tokens', 4000));
        $model = (string) config('ai.model');

        $http = Http::baseUrl((string) config('ai.base_url'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->connectTimeout((int) config('ai.connect_timeout', 10));

        if (! config('ai.verify_ssl', true)) {
            $http->withoutVerifying();
        }

        $logContext = [
            'door' => $meta['door'] ?? null,
            'stage' => $meta['stage'] ?? null,
            'attempt' => $meta['attempt'] ?? 1,
            'chart_id' => $meta['chart_id'] ?? null,
            'model' => $model,
            'endpoint' => '/chat/completions',
            'streaming' => false,
            'max_tokens_configured' => $maxTokens,
        ];

        $startedAt = microtime(true);

        try {
            $response = $http->post('/chat/completions', [
                'model' => $model,
                'temperature' => config('ai.temperature', 0.7),
                'max_completion_tokens' => $maxTokens,
                'response_format' => $responseFormat,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('OpenAI chat completion connection failure', [
                ...$logContext,
                'duration_ms' => $this->durationMs($startedAt),
                'message' => $exception->getMessage(),
            ]);
            throw new AiGenerationException(AiGenerationException::TIMEOUT, 'La llamada a la IA superó el tiempo de espera configurado.', $exception);
        }

        $usage = [
            'input_tokens' => (int) $response->json('usage.prompt_tokens', 0),
            'output_tokens' => (int) $response->json('usage.completion_tokens', 0),
            'total_tokens' => (int) $response->json('usage.total_tokens', 0),
        ];
        $finishReason = $response->json('choices.0.finish_reason');
        $logPayload = [
            ...$logContext,
            ...$usage,
            'duration_ms' => $this->durationMs($startedAt),
            'http_status' => $response->status(),
            'openai_request_id' => $response->header('x-request-id') ?: null,
            'finish_reason' => $finishReason,
            'error_type' => $response->json('error.type'),
            'error_code' => $response->json('error.code'),
        ];

        if ($response->failed()) {
            Log::warning('OpenAI chat completion HTTP error', [...$logPayload, 'message' => (string) $response->json('error.message', 'Error sin detalle')]);
            $errorCode = match (true) {
                $response->status() === 429 => AiGenerationException::RATE_LIMIT,
                $response->status() >= 500 => AiGenerationException::CONNECTION_ERROR,
                default => AiGenerationException::UNKNOWN_ERROR,
            };
            throw new AiGenerationException($errorCode, sprintf(
                'ChatGPT respondió con HTTP %d: %s',
                $response->status(),
                (string) $response->json('error.message', 'Error sin detalle'),
            ));
        }

        if ($finishReason !== 'stop') {
            Log::warning('OpenAI chat completion did not finish with stop', $logPayload);
            if ($finishReason === 'length') {
                throw new AiGenerationException(AiGenerationException::OUTPUT_LIMIT_REACHED, 'La respuesta de IA alcanzó el límite de tokens de salida configurado.');
            }
            throw new AiGenerationException(AiGenerationException::STREAM_INTERRUPTED, 'La respuesta de IA quedó incompleta o fue interrumpida.');
        }

        if ($response->json('choices.0.message.refusal')) {
            Log::warning('OpenAI chat completion refused', $logPayload);
            throw new AiGenerationException(AiGenerationException::UNKNOWN_ERROR, 'La API de IA rechazó la generación solicitada.');
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            Log::warning('OpenAI chat completion returned empty content', $logPayload);
            throw new AiGenerationException(AiGenerationException::STREAM_INTERRUPTED, 'La API de IA no devolvió contenido textual.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            Log::warning('OpenAI chat completion returned invalid JSON', $logPayload);
            throw new AiGenerationException(AiGenerationException::INVALID_JSON, 'La respuesta de IA no contiene JSON válido.');
        }

        Log::info('OpenAI chat completion succeeded', $logPayload);
        $stateName = strtok($meta['stage'] ?? '', '_');
        if (isset(ReportState::HEADINGS[$stateName])) {
            ReportTrace::record('raw_response_decoded', $decoded[$stateName] ?? [], $meta + ['section_id' => ($meta['door'] ?? '').'.'.$stateName]);
        }

        $decoded['_usage'] = $usage;

        return $decoded;
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
