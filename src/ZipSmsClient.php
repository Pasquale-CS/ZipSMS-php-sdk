<?php

declare(strict_types=1);

namespace ZipSms;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use ZipSms\Http\HttpClient;
use ZipSms\Resources\App;
use ZipSms\Resources\Devices;
use ZipSms\Resources\Sms;
use ZipSms\Resources\Usage;

/**
 * Entry-point dell'SDK.
 *
 * @example
 * $zip = ZipSmsClient::create('sk_live_...', 'https://zipsms-gateway.pcs.website');
 * $res = $zip->sms()->send('device-uuid', '+393331234567', 'Ciao da ZipSMS!');
 */
final class ZipSmsClient
{
    private readonly HttpClient $http;
    private ?Sms $sms = null;
    private ?Devices $devices = null;
    private ?Usage $usage = null;
    private ?App $app = null;

    public function __construct(
        private readonly Config $config,
        ?ClientInterface $httpClient = null,
    ) {
        $this->http = new HttpClient(
            $config,
            $httpClient ?? new GuzzleClient()
        );
    }

    /** Scorciatoia: client autenticato con API key. */
    public static function create(
        string $apiKey,
        string $baseUrl = 'https://zipsms-gateway.pcs.website',
        array $options = [],
    ): self {
        Config::assertCredential($apiKey, 'API key');
        return new self(
            Config::create(['api_key' => $apiKey, 'base_url' => $baseUrl] + $options),
            $options['http_client'] ?? null,
        );
    }

    /** Client con sessione Bearer (dashboard: devices/usage/invio manuale). */
    public static function withSession(
        string $bearerToken,
        string $baseUrl = 'https://zipsms-gateway.pcs.website',
        array $options = [],
    ): self {
        Config::assertCredential($bearerToken, 'Bearer token');
        return new self(
            Config::create(['bearer_token' => $bearerToken, 'base_url' => $baseUrl] + $options),
            $options['http_client'] ?? null,
        );
    }

    public function sms(): Sms
    {
        return $this->sms ??= new Sms($this->http);
    }

    public function devices(): Devices
    {
        return $this->devices ??= new Devices($this->http);
    }

    public function usage(): Usage
    {
        return $this->usage ??= new Usage($this->http);
    }

    public function app(): App
    {
        return $this->app ??= new App($this->http);
    }

    public function config(): Config
    {
        return $this->config;
    }
}
