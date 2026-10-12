<?php

declare(strict_types=1);

namespace ZipSms\Resources;

use ZipSms\Http\HttpClient;

/** Quote e versione app (supporto dashboard). */
final class Usage
{
    public function __construct(private readonly HttpClient $http) {}

    /** Uso e residuo quote API/dashboard del mese (auth: Bearer). */
    public function get(): array
    {
        return $this->http->request('GET', '/api/v1/usage');
    }
}
