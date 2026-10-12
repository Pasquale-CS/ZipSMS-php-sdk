<?php

declare(strict_types=1);

namespace ZipSms\Resources;

use ZipSms\Http\HttpClient;

/** Endpoint pubblico: versione minima e URL APK. */
final class App
{
    public function __construct(private readonly HttpClient $http) {}

    public function version(): array
    {
        return $this->http->request('GET', '/api/v1/app/version');
    }
}
