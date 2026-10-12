<?php

declare(strict_types=1);

namespace ZipSms\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use ZipSms\Config;
use ZipSms\Exceptions\AuthenticationException;
use ZipSms\Exceptions\ConflictException;
use ZipSms\Exceptions\NetworkException;
use ZipSms\Exceptions\NotFoundException;
use ZipSms\Exceptions\RateLimitException;
use ZipSms\Exceptions\ServerException;
use ZipSms\Exceptions\ValidationException;
use ZipSms\Exceptions\ZipSmsException;

/**
 * Wrapper HTTP: auth headers, User-Agent, TLS verify, mapping errori.
 *
 * Non logga mai le credenziali; i messaggi di errore espongono solo
 * {message, code} del body senza header sensibili.
 */
final class HttpClient
{
    public const SDK_VERSION = '0.1.0';

    public function __construct(
        private readonly Config $config,
        private readonly ClientInterface $http,
    ) {}

    /**
     * @param array<string,mixed> $options opzioni Guzzle extra (query, json, headers)
     * @return array<string,mixed> body JSON decodificato ([] per 204/vuoto)
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $headers = $options['headers'] ?? [];
        \assert(\is_array($headers));

        $headers['Accept'] = 'application/json';
        $headers['User-Agent'] = 'zipsms-php-sdk/' . self::SDK_VERSION . ' (PHP ' . PHP_VERSION . ')';

        if ($this->config->apiKey() !== null && !isset($headers['x-api-key'], $headers['X-Api-Key'])) {
            $headers['x-api-key'] = $this->config->apiKey();
        }
        if ($this->config->bearerToken() !== null && !isset($headers['Authorization'])) {
            $headers['Authorization'] = 'Bearer ' . $this->config->bearerToken();
        }

        $options['headers'] = $headers;
        $options['timeout'] = $options['timeout'] ?? $this->config->timeout();
        $options['connect_timeout'] = $options['connect_timeout'] ?? $this->config->connectTimeout();
        // TLS sempre verificato (anti-MITM). Guzzle lo fa di default; lo forziamo.
        $options['verify'] = $options['verify'] ?? true;
        $options['http_errors'] = false;

        $url = $this->config->baseUrl() . '/' . ltrim($path, '/');

        try {
            $response = $this->http->request($method, $url, $options);
        } catch (ConnectException $e) {
            throw new NetworkException('Connessione al gateway fallita: ' . $this->safeReason($e), 0, $e);
        } catch (RequestException $e) {
            throw new NetworkException('Errore di rete: ' . $this->safeReason($e), 0, $e);
        } catch (\Throwable $e) {
            throw new NetworkException('Errore HTTP imprevisto: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status === 204 || trim($body) === '') {
            if ($status >= 200 && $status < 300) {
                return [];
            }
            throw $this->mapError($status, null, $response->getHeaderLine('Retry-After'));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ServerException('Risposta non-JSON dal server (HTTP ' . $status . ').', null, $status, $e);
        }

        if ($status >= 200 && $status < 300) {
            return \is_array($decoded) ? $decoded : ['data' => $decoded];
        }

        $payload = \is_array($decoded) ? $decoded : null;
        throw $this->mapError($status, $payload, $response->getHeaderLine('Retry-After'));
    }

    private function safeReason(\Throwable $e): string
    {
        // I messaggi Guzzle possono includere l'URL ma mai le credenziali
        // (viaggiano negli header). Tagliamo comunque a 300 char.
        return mb_substr($e->getMessage(), 0, 300);
    }

    /** @param array<string,mixed>|null $payload */
    private function mapError(int $status, ?array $payload, string $retryAfter): ZipSmsException
    {
        $message = $this->extractMessage($payload, $status);
        $code = $this->extractCode($payload);

        return match (true) {
            $status === 400 || $status === 422 => new ValidationException($message, $code, $status),
            $status === 401 || $status === 403 => new AuthenticationException($message, $code, $status),
            $status === 404 => new NotFoundException($message, $code, $status),
            $status === 409 => new ConflictException($message, $code, $status),
            $status === 429 => new RateLimitException($message, $code, $status, null, $this->parseRetryAfter($retryAfter)),
            $status >= 500 => new ServerException($message, $code, $status),
            default => new ZipSmsException($message, $code, $status),
        };
    }

    /** @param array<string,mixed>|null $payload */
    private function extractMessage(?array $payload, int $status): string
    {
        if ($payload !== null) {
            foreach (['message', 'error', 'detail'] as $key) {
                if (isset($payload[$key]) && \is_string($payload[$key]) && trim($payload[$key]) !== '') {
                    return mb_substr(trim($payload[$key]), 0, 500);
                }
            }
            if (isset($payload['errors']) && \is_array($payload['errors'])) {
                $first = $this->firstString($payload['errors']);
                if ($first !== null) {
                    return mb_substr($first, 0, 500);
                }
            }
        }
        return "Richiesta fallita (HTTP {$status}).";
    }

    /** @param array<string,mixed>|null $payload */
    private function extractCode(?array $payload): ?string
    {
        if ($payload !== null && isset($payload['code']) && \is_string($payload['code'])) {
            $code = trim($payload['code']);
            // Whitelist sobria: evita di propagare blob arbitrari.
            if (preg_match('/^[A-Z0-9_]{1,64}$/', $code) === 1) {
                return $code;
            }
        }
        return null;
    }

    /** @param array<mixed> $errors */
    private function firstString(array $errors): ?string
    {
        foreach ($errors as $v) {
            if (\is_string($v) && trim($v) !== '') {
                return trim($v);
            }
            if (\is_array($v)) {
                $nested = $this->firstString($v);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }
        return null;
    }

    private function parseRetryAfter(string $header): ?int
    {
        $h = trim($header);
        if ($h === '') {
            return null;
        }
        if (ctype_digit($h)) {
            return (int) $h;
        }
        $ts = strtotime($h);
        if ($ts !== false) {
            return max(0, $ts - time());
        }
        return null;
    }
}
