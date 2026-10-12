<?php

declare(strict_types=1);

namespace ZipSms\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ZipSms\Config;
use ZipSms\Exceptions\AuthenticationException;
use ZipSms\Exceptions\RateLimitException;
use ZipSms\Exceptions\ValidationException;
use ZipSms\ZipSmsClient;

final class SmsTest extends TestCase
{
    /** @var array<int,array<string,mixed>> */
    private array $history = [];

    private function clientWithResponses(Response ...$responses): ZipSmsClient
    {
        $this->history = [];
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new Client(['handler' => $stack]);

        return new ZipSmsClient(
            new Config(baseUrl: 'http://localhost:3001', apiKey: 'sk_test_1234567890'),
            $guzzle
        );
    }

    public function testSendPostsCorrectShape(): void
    {
        $client = $this->clientWithResponses(
            new Response(202, ['Content-Type' => 'application/json'], '{"id":"uuid-1","status":"QUEUED","segments":1}')
        );

        $res = $client->sms()->send('+39 333 123 4567', 'Ciao!', 'dev-1', null, 'ordine-1', 'key-123');

        $this->assertSame('uuid-1', $res['id']);
        $this->assertCount(1, $this->history);

        $req = $this->history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('/api/v1/sms/send', $req->getUri()->getPath());
        $this->assertSame('key-123', $req->getHeaderLine('Idempotency-Key'));
        $this->assertSame('sk_test_1234567890', $req->getHeaderLine('x-api-key'));

        $body = json_decode((string) $req->getBody(), true);
        $this->assertSame('+393331234567', $body['to']); // normalizzato E.164
        $this->assertSame('dev-1', $body['deviceId']);
    }

    public function testSendAutoGeneratesIdempotencyKey(): void
    {
        $client = $this->clientWithResponses(
            new Response(202, ['Content-Type' => 'application/json'], '{"id":"uuid-2","status":"QUEUED"}')
        );
        $client->sms()->send('+393331234567', 'Ciao!', deviceId: 'dev-1');

        $key = $this->history[0]['request']->getHeaderLine('Idempotency-Key');
        $this->assertNotSame('', $key);
        $this->assertStringNotContainsString("\n", $key);
    }

    public function testSendValidatesBeforeHttp(): void
    {
        $client = $this->clientWithResponses(
            new Response(202, [], '{}')
        );
        $this->expectException(ValidationException::class);
        $client->sms()->send('not-a-number', 'Ciao!', deviceId: 'dev-1');
        $this->assertCount(0, $this->history); // nessuna chiamata HTTP
    }

    public function testSendByDeviceName(): void
    {
        $client = $this->clientWithResponses(
            new Response(202, ['Content-Type' => 'application/json'], '{"id":"uuid-3","status":"QUEUED"}')
        );

        $client->sms()->send('+393331234567', 'Ciao!', deviceName: 'ufficio-1');

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame('ufficio-1', $body['deviceName']);
        $this->assertArrayNotHasKey('deviceId', $body);
    }

    public function testSendRejectsBothTargets(): void
    {
        $client = $this->clientWithResponses(new Response(202, [], '{}'));
        try {
            $client->sms()->send('+393331234567', 'Ciao!', 'dev-1', 'ufficio-1');
            $this->fail('attesa ValidationException');
        } catch (ValidationException $e) {
            $this->assertCount(0, $this->history); // nessuna chiamata HTTP
            $this->assertStringContainsString('esattamente uno', $e->getMessage());
        }
    }

    public function testSendRejectsMissingTarget(): void
    {
        $client = $this->clientWithResponses(new Response(202, [], '{}'));
        try {
            $client->sms()->send('+393331234567', 'Ciao!');
            $this->fail('attesa ValidationException');
        } catch (ValidationException $e) {
            $this->assertCount(0, $this->history); // nessuna chiamata HTTP
            $this->assertStringContainsString('esattamente uno', $e->getMessage());
        }
    }

    public function testMaps401(): void
    {
        $client = $this->clientWithResponses(
            new Response(401, ['Content-Type' => 'application/json'], '{"message":"API key non valida","code":"INVALID_API_KEY"}')
        );
        try {
            $client->sms()->get('sms-id');
            $this->fail('attesa AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(401, $e->statusCode());
            $this->assertSame('INVALID_API_KEY', $e->errorCode());
            // Il messaggio non deve contenere la chiave
            $this->assertStringNotContainsString('sk_test', $e->getMessage());
        }
    }

    public function testMaps429WithRetryAfter(): void
    {
        $client = $this->clientWithResponses(
            new Response(429, ['Content-Type' => 'application/json', 'Retry-After' => '42'], '{"message":"Quota esaurita","code":"API_QUOTA_EXCEEDED"}')
        );
        try {
            $client->sms()->get('sms-id');
            $this->fail('attesa RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(42, $e->retryAfterSeconds());
            $this->assertSame('API_QUOTA_EXCEEDED', $e->errorCode());
        }
    }
}
