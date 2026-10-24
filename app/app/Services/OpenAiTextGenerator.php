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
        $metrics = app(ReportMetrics::class);
        $id = $metrics->begin($meta['report_job_id'] ?? null, 'ai', ($meta['door'] ?? 'unknown').'.'.($meta['stage'] ?? 'generation'), (string) config('ai.model'));
        $started = hrtime(true);
        $usage = [];
        $error = null;
        try {
            return $this->performRequest($systemPrompt, $userPrompt, $responseFormat, $meta, $usage);
        } catch (\Throwable $exception) {
            $error = $exception;
            throw $exception;
        } finally {
            $metrics->finish($id, $started, $error, $usage);
        }
    }

    private function performRequest(string $systemPrompt, string $userPrompt, array $responseFormat, array $meta, array &$usage): array
    {
        $apiKey = (string) config('ai.api_key');

        if ($apiKey === '') {
            throw new AiGenerationException(AiGenerationException::UNKNOWN_ERROR, 'AI_API_KEY no está configurada.');
        }

        $isStructured = $responseFormat['type'] === 'json_schema';
        // Each call has its own deadline; report orchestration runs in the queue worker.
        $audit = $isStructured && ($meta['stage'] ?? null) === 'natal_audit';
        $timeout = (int) ($isStructured ? ($audit ? config('ai.audit_timeout', 300) : config('ai.stage_timeout', 95)) : config('ai.timeout', 90));
        $maxTokens = (int) ($isStructured
            ? ($audit ? config('ai.audit_max_completion_tokens', 16000) : config('ai.chapter_max_completion_tokens', 10000))
            : config('ai.door_max_completion_tokens', 4000));
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

        $payload = [
            'model' => $model,
            'max_completion_tokens' => $maxTokens,
            'response_format' => $responseFormat,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];
        if (! preg_match('/^(?:gpt-5(?:-mini|-nano)?(?:-\d{4}-\d{2}-\d{2})?|o[134](?:-mini)?(?:-\d{4}-\d{2}-\d{2})?)$/', $model)) {
            $payload['temperature'] = config('ai.temperature', 0.7);
        }

        try {
            $response = $http->post('/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            $timedOut = str_contains($exception->getMessage(), 'cURL error 28');
            Log::warning('OpenAI chat completion connection failure', [
                ...$logContext,
                'duration_ms' => $this->durationMs($startedAt),
                'error_code' => 'CONNECTION_FAILURE',
            ]);
            throw new AiGenerationException(
                $timedOut ? AiGenerationException::TIMEOUT : AiGenerationException::CONNECTION_ERROR,
                $timedOut ? 'La llamada a la IA superó el tiempo de espera configurado.' : 'No se pudo conectar con OpenAI. Revisa la red y la conexión TLS.',
                $exception,
            );
        }

        $usage = [
            'input_tokens' => (int) $response->json('usage.prompt_tokens', 0),
            'output_tokens' => (int) $response->json('usage.completion_tokens', 0),
            'total_tokens' => (int) $response->json('usage.total_tokens', 0),
            'reasoning_tokens' => (int) $response->json('usage.completion_tokens_details.reasoning_tokens', 0),
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
            'error_param' => $response->json('error.param'),
        ];

        if ($response->failed()) {
            Log::warning('OpenAI chat completion HTTP error', $logPayload);
            $errorCode = match (true) {
                $response->json('error.type') === 'insufficient_quota', $response->json('error.code') === 'insufficient_quota' => AiGenerationException::QUOTA_EXCEEDED,
                in_array($response->status(), [401, 403], true) => AiGenerationException::AUTHENTICATION_ERROR,
                $response->status() === 429 => AiGenerationException::RATE_LIMIT,
                $response->status() === 408 => AiGenerationException::TIMEOUT,
                $response->status() >= 500 => AiGenerationException::CONNECTION_ERROR,
                default => AiGenerationException::INVALID_REQUEST,
            };
            $message = match ($errorCode) {
                AiGenerationException::QUOTA_EXCEEDED => 'OpenAI no dispone de cuota o crédito suficiente. Revisa la facturación antes de reintentar.',
                AiGenerationException::AUTHENTICATION_ERROR => 'OpenAI rechazó la autenticación o los permisos. Revisa la clave y el acceso al modelo.',
                AiGenerationException::RATE_LIMIT => 'OpenAI ha limitado temporalmente las solicitudes. Se reintentará con una pausa.',
                AiGenerationException::TIMEOUT => 'OpenAI agotó el tiempo de la solicitud.',
                AiGenerationException::CONNECTION_ERROR => 'OpenAI presenta un error temporal del servidor.',
                default => 'OpenAI rechazó la solicitud. Revisa el modelo, los parámetros y el esquema JSON en la configuración.',
            };
            throw new AiGenerationException($errorCode, $message.' HTTP '.$response->status().'.');
        }

        if ($response->json('choices.0.message.refusal') || $finishReason === 'content_filter') {
            Log::warning('OpenAI chat completion refused', $logPayload);
            throw new AiGenerationException(AiGenerationException::REFUSED, 'OpenAI rechazó el contenido solicitado. Revisa la solicitud antes de reintentar.');
        }

        if ($finishReason !== 'stop') {
            Log::warning('OpenAI chat completion did not finish with stop', $logPayload);
            if ($finishReason === 'length') {
                throw new AiGenerationException(AiGenerationException::OUTPUT_LIMIT_REACHED, 'La respuesta de IA alcanzó el límite de tokens, que incluye razonamiento y texto. Ajusta el presupuesto de salida antes de reintentar.');
            }
            throw new AiGenerationException(AiGenerationException::STREAM_INTERRUPTED, 'La respuesta de IA quedó incompleta o fue interrumpida.');
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
        $decoded['_usage'] = $usage;

        return $decoded;
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
