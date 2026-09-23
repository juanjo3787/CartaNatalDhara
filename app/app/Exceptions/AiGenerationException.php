<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class AiGenerationException extends RuntimeException
{
    public const OUTPUT_LIMIT_REACHED = 'AI_OUTPUT_LIMIT_REACHED';
    public const STREAM_INTERRUPTED = 'AI_STREAM_INTERRUPTED';
    public const TIMEOUT = 'AI_TIMEOUT';
    public const RATE_LIMIT = 'AI_RATE_LIMIT';
    public const INVALID_JSON = 'AI_INVALID_JSON';
    public const SCHEMA_VALIDATION_FAILED = 'AI_SCHEMA_VALIDATION_FAILED';
    public const CONNECTION_ERROR = 'AI_CONNECTION_ERROR';
    public const UNKNOWN_ERROR = 'AI_UNKNOWN_ERROR';

    /** Recoverable with a plain retry (exponential backoff). Output-limit errors are excluded on purpose. */
    private const RETRYABLE = [self::TIMEOUT, self::RATE_LIMIT, self::CONNECTION_ERROR, self::STREAM_INTERRUPTED];

    public function __construct(public readonly string $errorCode, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function isRetryable(): bool
    {
        return in_array($this->errorCode, self::RETRYABLE, true);
    }
}
