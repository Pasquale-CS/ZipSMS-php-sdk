<?php

declare(strict_types=1);

namespace ZipSms\Validation;

use ZipSms\Exceptions\ValidationException;

/**
 * Validazione client-side di tutti gli input (fail-fast + sicurezza).
 *
 * - Il telefono viene normalizzato (rimossi spazi/trattini/punti) e deve
 *   rispettare E.164: ^\+[1-9]\d{7,14}$  (max 15 cifre dopo il +).
 * - Il messaggio: 1..1600 caratteri (mb_strlen), niente solo-spazi.
 * - Idempotency-Key: se assente viene generato un UUIDv4; se fornita deve
 *   essere 1..64 char stampabili senza spazi (sicura come header HTTP).
 */
final class Validator
{
    public const MESSAGE_MAX_LENGTH = 1600;
    public const REFERENCE_MAX_LENGTH = 128;
    public const DEVICE_NAME_MAX_LENGTH = 32;
    public const IDEMPOTENCY_KEY_MAX_LENGTH = 64;

    /** Normalizza + valida un numero E.164. Restituisce la forma canonica. */
    public static function phone(string $to): string
    {
        // Rimuove separatori innocui (lo fa anche il server, ma normalizziamo qui).
        $normalized = preg_replace('/[\s\-.\(\)]/', '', trim($to));
        if ($normalized === null || $normalized === '') {
            throw new ValidationException('Destinatario non valido: numero vuoto.');
        }
        if (preg_match('/^\+[1-9]\d{7,14}$/', $normalized) !== 1) {
            throw new ValidationException(
                'Destinatario non valido: usare il formato E.164 (es. +393331234567).'
            );
        }
        return $normalized;
    }

    /** @return string testo trimmato valido */
    public static function message(string $message): string
    {
        $text = trim($message);
        if ($text === '') {
            throw new ValidationException('Messaggio non valido: testo vuoto.');
        }
        $len = mb_strlen($text, 'UTF-8');
        if ($len > self::MESSAGE_MAX_LENGTH) {
            throw new ValidationException(
                'Messaggio non valido: max ' . self::MESSAGE_MAX_LENGTH . " caratteri ({$len} forniti)."
            );
        }
        // Rigetta stringhe con soli caratteri di controllo.
        if (preg_match('/^[\p{C}\s]+$/u', $text) === 1) {
            throw new ValidationException('Messaggio non valido: solo caratteri di controllo.');
        }
        return $text;
    }

    public static function deviceId(string $deviceId): string
    {
        $id = trim($deviceId);
        if ($id === '' || strlen($id) > 128) {
            throw new ValidationException('deviceId non valido: richiesto 1..128 caratteri.');
        }
        if (preg_match('/^[\w\-.:\/+=]+$/', $id) !== 1) {
            throw new ValidationException('deviceId non valido: caratteri consentiti [A-Za-z0-9_-. :/+=].');
        }
        return $id;
    }

    public static function smsId(string $id): string
    {
        $v = trim($id);
        if ($v === '' || strlen($v) > 128) {
            throw new ValidationException('SMS id non valido.');
        }
        return $v;
    }

    public static function reference(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }
        $ref = trim($reference);
        if ($ref === '') {
            return null;
        }
        if (mb_strlen($ref, 'UTF-8') > self::REFERENCE_MAX_LENGTH) {
            throw new ValidationException('reference troppo lungo: max ' . self::REFERENCE_MAX_LENGTH . ' caratteri.');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $ref) === 1) {
            throw new ValidationException('reference non valido: contiene caratteri di controllo.');
        }
        return $ref;
    }

    public static function deviceName(string $name): string
    {
        $n = trim($name);
        if ($n === '' || mb_strlen($n, 'UTF-8') > self::DEVICE_NAME_MAX_LENGTH) {
            throw new ValidationException('Nome device non valido: 1..' . self::DEVICE_NAME_MAX_LENGTH . ' caratteri.');
        }
        // Il server richiede [a-z0-9-_]; forziamolo qui per fail-fast.
        if (preg_match('/^[a-z0-9\-_]+$/', $n) !== 1) {
            throw new ValidationException('Nome device non valido: usare solo [a-z0-9-_], max 32.');
        }
        return $n;
    }

    public static function fcmToken(string $token): string
    {
        $t = trim($token);
        if ($t === '' || strlen($t) > 4096) {
            throw new ValidationException('FCM token non valido.');
        }
        if (preg_match('/[\s\x00-\x1F\x7F]/', $t) === 1) {
            throw new ValidationException('FCM token non valido: contiene spazi o caratteri di controllo.');
        }
        return $t;
    }

    /** Genera un UUIDv4 (Idempotency-Key di default). */
    public static function generateIdempotencyKey(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /** Valida (o genera) la chiave di idempotenza — sicura come header HTTP. */
    public static function idempotencyKey(?string $key): string
    {
        if ($key === null || trim($key) === '') {
            return self::generateIdempotencyKey();
        }
        $k = trim($key);
        if (strlen($k) > self::IDEMPOTENCY_KEY_MAX_LENGTH) {
            throw new ValidationException('Idempotency-Key troppo lunga: max ' . self::IDEMPOTENCY_KEY_MAX_LENGTH . '.');
        }
        // Solo ASCII stampabile senza spazi: sicura in header, niente CRLF injection.
        if (preg_match('/^[!-~]+$/', $k) !== 1) {
            throw new ValidationException('Idempotency-Key non valida: solo ASCII stampabile, senza spazi.');
        }
        return $k;
    }

    /** @param string[] $ids */
    public static function deviceIds(array $ids, int $max = 50): array
    {
        if ($ids === [] || \count($ids) > $max) {
            throw new ValidationException("deviceIds non valido: servono 1..{$max} elementi.");
        }
        return array_values(array_map([self::class, 'deviceId'], $ids));
    }

    /**
     * Target del device per l'invio: esattamente uno tra deviceId e deviceName
     * (mirror del DTO SendSmsR1Dto). Restituisce il frammento di payload.
     *
     * @return array{deviceId?:string,deviceName?:string}
     */
    public static function deviceTarget(?string $deviceId, ?string $deviceName): array
    {
        $hasId = $deviceId !== null && trim($deviceId) !== '';
        $hasName = $deviceName !== null && trim($deviceName) !== '';

        if ($hasId === $hasName) {
            throw new ValidationException(
                'Indicare esattamente uno tra deviceId e deviceName (uno solo, non entrambi).'
            );
        }

        if ($hasId) {
            return ['deviceId' => self::deviceId($deviceId)];
        }

        return ['deviceName' => self::deviceName($deviceName)];
    }
}
