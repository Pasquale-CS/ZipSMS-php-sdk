<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ZipSms\Exceptions\RateLimitException;
use ZipSms\Exceptions\ZipSmsException;
use ZipSms\ZipSmsClient;

// Esempio base: invio SMS con API key.
// Sicurezza: la chiave arriva da env, mai hardcodata.
$client = ZipSmsClient::create(
    $_ENV['ZIPSMS_API_KEY'] ?? getenv('ZIPSMS_API_KEY') ?: throw new RuntimeException('Imposta ZIPSMS_API_KEY'),
    $_ENV['ZIPSMS_BASE_URL'] ?? getenv('ZIPSMS_BASE_URL') ?: 'https://zipsms-gateway.pcs.website',
);

try {
    $res = $client->sms()->send(
        deviceId: 'tuo-device-id',
        to: '+393331234567',
        message: 'Ciao da ZipSMS!',
        reference: 'ordine-123',       // opzionale: tuo riferimento interno
        // idempotencyKey: null → UUIDv4 auto-generato. Passane uno tuo
        // (es. UUID dell'ordine) per retry sicuri senza doppi invii.
    );
    echo 'Accodato: ' . ($res['id'] ?? '?') . ' (' . ($res['status'] ?? '?') . ")\n";

    // Polling stato:
    $detail = $client->sms()->get($res['id']);
    echo 'Stato: ' . ($detail['status'] ?? '?') . "\n";
} catch (RateLimitException $e) {
    fwrite(STDERR, "Quota/rate-limit ({$e->errorCode()}). Retry tra {$e->retryAfterSeconds()}s.\n");
    exit(1);
} catch (ZipSmsException $e) {
    fwrite(STDERR, "Errore ZipSMS [{$e->statusCode()}/{$e->errorCode()}]: {$e->getMessage()}\n");
    exit(1);
}
