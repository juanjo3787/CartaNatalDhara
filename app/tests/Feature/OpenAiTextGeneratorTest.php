<?php

namespace Tests\Feature;

use App\Exceptions\AiGenerationException;
use App\Services\InteriorMapMaster;
use App\Services\OpenAiTextGenerator;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpenAiTextGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.api_key' => 'test-key', 'ai.model' => 'gpt-5-mini', 'ai.temperature' => 0.7]);
        Http::preventStrayRequests();
    }

    #[DataProvider('models')]
    public function test_request_parameters_match_the_model(string $model, bool $temperature): void
    {
        config(['ai.model' => $model]);
        Http::fake(['*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"concepts":[]}']]], 'usage' => ['completion_tokens' => 100, 'completion_tokens_details' => ['reasoning_tokens' => 60]]])]);
        $result = app(OpenAiTextGenerator::class)->generateStructured('system', 'user', (new InteriorMapMaster)->schema());
        $this->assertSame(60, $result['_usage']['reasoning_tokens']);
        Http::assertSent(function ($request) use ($temperature) {
            $this->assertSame($temperature, array_key_exists('temperature', $request->data()));
            $this->assertTrue($request['response_format']['json_schema']['strict']);
            $this->assertSame(10000, $request['max_completion_tokens']);

            return true;
        });
    }

    public static function models(): array
    {
        return [['gpt-5-mini', false], ['gpt-5-mini-2025-08-07', false], ['gpt-5', false], ['gpt-4o-mini', true]];
    }

    #[DataProvider('errors')]
    public function test_provider_errors_are_classified_without_exposing_raw_messages(int $status, array $body, string $code, bool $retryable): void
    {
        Http::fake(['*' => Http::response($body, $status)]);
        try {
            app(OpenAiTextGenerator::class)->generate('system', 'user');
            $this->fail('The provider response must be rejected.');
        } catch (AiGenerationException $exception) {
            $this->assertSame($code, $exception->errorCode);
            $this->assertSame($retryable, $exception->isRetryable());
            $this->assertStringNotContainsString('private-provider-detail', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public static function errors(): array
    {
        return [
            [429, ['error' => ['type' => 'insufficient_quota']], AiGenerationException::QUOTA_EXCEEDED, false],
            [429, ['error' => ['code' => 'rate_limit_exceeded']], AiGenerationException::RATE_LIMIT, true],
            [401, ['error' => ['message' => 'private-provider-detail']], AiGenerationException::AUTHENTICATION_ERROR, false],
            [400, ['error' => ['param' => 'temperature']], AiGenerationException::INVALID_REQUEST, false],
            [503, [], AiGenerationException::CONNECTION_ERROR, true],
            [200, ['choices' => [['finish_reason' => 'length', 'message' => ['content' => '{']]]], AiGenerationException::OUTPUT_LIMIT_REACHED, false],
            [200, ['choices' => [['finish_reason' => 'content_filter', 'message' => ['content' => null]]]], AiGenerationException::REFUSED, false],
            [200, ['choices' => [['finish_reason' => 'stop', 'message' => ['refusal' => 'private-provider-detail']]]], AiGenerationException::REFUSED, false],
            [200, ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{']]]], AiGenerationException::INVALID_JSON, false],
        ];
    }
}
