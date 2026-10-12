<?php

declare(strict_types=1);

namespace ZipSms;

/**
 * Configurazione immutabile del client.
 *
 * Sicurezza:
 * - baseUrl deve essere HTTPS, tranne host locali (localhost, 127.0.0.1, ::1,
 *   *.localhost, *.local, *.test) utili per sviluppo/test.
 * - Le credenziali vengono trimmate e validate (niente whitespace interno,
 *   niente caratteri di controllo → previene header injection).
 */
final class Config
{
    private const DEFAULT_BASE_URL = 'https://zipsms-gateway.pcs.website';
    private const DEFAULT_TIMEOUT = 15.0;
    private const DEFAULT_CONNECT_TIMEOUT = 5.0;

    public function __construct(
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly ?string $apiKey = null,
        private readonly ?string $bearerToken = null,
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
        private readonly float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
    ) {
        $this->assertValidBaseUrl($this->baseUrl);
        $this->assertValidTimeout($this->timeout, 'timeout');
        $this->assertValidTimeout($this->connectTimeout, 'connect_timeout');

        if ($this->apiKey !== null) {
            self::assertCredential($this->apiKey, 'API key');
        }
        if ($this->bearerToken !== null) {
            self::assertCredential($this->bearerToken, 'Bearer token');
        }
    }

    /**
     * @param array{base_url?:string,baseUrl?:string,api_key?:string,apiKey?:string,bearer_token?:string,bearerToken?:string,session_token?:string,timeout?:int|float,connect_timeout?:int|float} $values
     */
    public static function create(array $values = []): self
    {
        return new self(
            baseUrl: $values['base_url'] ?? $values['baseUrl'] ?? self::DEFAULT_BASE_URL,
            apiKey: $values['api_key'] ?? $values['apiKey'] ?? null,
            bearerToken: $values['bearer_token'] ?? $values['bearerToken'] ?? $values['session_token'] ?? null,
            timeout: isset($values['timeout']) ? (float) $values['timeout'] : self::DEFAULT_TIMEOUT,
            connectTimeout: isset($values['connect_timeout']) ? (float) $values['connect_timeout'] : self::DEFAULT_CONNECT_TIMEOUT,
        );
    }

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    public function bearerToken(): ?string
    {
        return $this->bearerToken;
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    public function connectTimeout(): float
    {
        return $this->connectTimeout;
    }

    public function withApiKey(string $apiKey): self
    {
        return new self($this->baseUrl, $apiKey, $this->bearerToken, $this->timeout, $this->connectTimeout);
    }

    public function withBearerToken(string $token): self
    {
        return new self($this->baseUrl, $this->apiKey, $token, $this->timeout, $this->connectTimeout);
    }

    // --- validazione interna ---

    private function assertValidBaseUrl(string $url): void
    {
        $parts = parse_url(trim($url));
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new Exceptions\ValidationException('baseUrl non valido: serve un URL assoluto (es. https://api.zipsms.it).');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);

        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new Exceptions\ValidationException('baseUrl non valido: schema consentiti http/https.');
        }

        if ($scheme === 'http' && !self::isLocalHost($host)) {
            // Enforce HTTPS contro MITM/sniffing della API key in produzione.
            throw new Exceptions\ValidationException(
                'baseUrl non sicuro: è richiesto HTTPS (http consentito solo per localhost/127.0.0.1/*.local/*.test).'
            );
        }
    }

    private static function isLocalHost(string $host): bool
    {
        if (\in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }
        foreach (['.localhost', '.local', '.test', '.example', '.invalid'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }
        return false;
    }

    private function assertValidTimeout(float $value, string $name): void
    {
        if ($value < 1.0 || $value > 120.0) {
            throw new Exceptions\ValidationException("{$name} deve essere tra 1 e 120 secondi.");
        }
    }

    /**
     * Rigetta credenziali vuote, con whitespace interno o caratteri di controllo
     * (previene CRLF/header injection e copia-incolla corrotti).
     */
    public static function assertCredential(string $value, string $label): void
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed !== $value) {
            throw new Exceptions\ValidationException("{$label} non valida: non deve essere vuota né contenere spazi ai bordi.");
        }
        if (strlen($trimmed) < 8) {
            throw new Exceptions\ValidationException("{$label} non valida: valore troppo corto.");
        }
        if (preg_match('/[\s\x00-\x1F\x7F]/', $trimmed) === 1) {
            throw new Exceptions\ValidationException("{$label} non valida: contiene caratteri non consentiti.");
        }
    }
}
