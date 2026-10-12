<?php

declare(strict_types=1);

namespace ZipSms\Exceptions;

class RateLimitException extends ZipSmsException
{
    public function __construct(
        string $message,
        ?string $errorCode = null,
        int $statusCode = 429,
        ?\Throwable $previous = null,
        private readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message, $errorCode, $statusCode, $previous);
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
