<?php

declare(strict_types=1);

namespace ZipSms\Exceptions;

/** Base di tutte le eccezioni dell'SDK. Il messaggio non contiene mai segreti. */
class ZipSmsException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $errorCode = null,
        private readonly int $statusCode = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
