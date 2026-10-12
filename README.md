# ZipSMS PHP SDK

SDK PHP ufficiale per **ZipSMS Gateway** — invio SMS tramite il proprio dispositivo Android via API.

- PHP `>= 8.1`, HTTP via `guzzlehttp/guzzle`
- Integrazione Laravel: ServiceProvider + Facade + `config/zipsms.php`
- Validazione client-side + eccezioni tipizzate + controlli di sicurezza

## Installazione

```bash
composer require pasquale-cs/zipsms-php-sdk
```

## Uso rapido

```php
use ZipSms\ZipSmsClient;

$zip = ZipSmsClient::create($_ENV['ZIPSMS_API_KEY']); // base URL di default: https://zipsms-gateway.pcs.website

$res = $zip->sms()->send(
    to: '+393331234567',
    message: 'Ciao da ZipSMS!',
    deviceName: 'ufficio-1',  // oppure deviceId: 'tuo-device-id' (esattamente uno)
    reference: 'ordine-123', // opzionale
    // idempotencyKey: null → UUIDv4 automatico
);

echo $res['id'] . ' ' . $res['status']; // es. QUEUED
```

### Idempotenza (importante)

`POST /sms/send` richiede l'header `Idempotency-Key`. L'SDK lo genera da solo
(UUIDv4) se non lo passi — ma per retry sicuri senza doppi invii, passa una
chiave stabile legata al tuo dominio (es. `ordine-123`):

```php
$zip->sms()->send($to, $msg, deviceId: $deviceId, reference: 'ordine-123', idempotencyKey: 'ordine-123');
```

### Stato / lista SMS

```php
$zip->sms()->get($smsId);
$zip->sms()->list(['status' => 'QUEUED', 'page' => 1, 'limit' => 20]);
```

### Device / Usage (sessione Bearer dashboard)

```php
$dash = ZipSmsClient::withSession($_ENV['ZIPSMS_SESSION_TOKEN']);

$dash->devices()->list();
$dash->devices()->create('ufficio-1', $fcmToken); // conserva il deviceToken cifrato!
$dash->devices()->rename($id, 'ufficio-2');
$dash->devices()->remove($id);
$dash->usage()->get();
$dash->app()->version(); // pubblico, niente auth
```

## Laravel

Auto-discovery attivo. Pubblica la config:

```bash
php artisan vendor:publish --tag=zipsms-config
```

```env
ZIPSMS_API_KEY=sk_live_...
ZIPSMS_SESSION_TOKEN=  # opzionale, per dashboard
ZIPSMS_BASE_URL=https://zipsms-gateway.pcs.website
```

```php
use ZipSms\Laravel\Facades\ZipSms;

ZipSms::sms()->send('+393331234567', 'Ciao!', deviceName: 'ufficio-1');
```

## Gestione errori

| HTTP | Eccezione | Quando |
|------|-----------|--------|
| 400/422 | `ValidationException` | E.164 errato, testo >1600, deviceId/deviceName… |
| 401/403 | `AuthenticationException` | API key mancante/revocata |
| 404 | `NotFoundException` | Device/SMS non tuo |
| 409 | `ConflictException` | Device revocato, transizione illegale |
| 429 | `RateLimitException` | Quota esaurita / rate limit (`->retryAfterSeconds()`, `->errorCode()` = `API_QUOTA_EXCEEDED`…) |
| 5xx/rete | `ServerException` / `NetworkException` | — |

Tutte estendono `ZipSmsException` (`->statusCode()`, `->errorCode()`). I messaggi
non contengono mai segreti.

## Sicurezza (applicata dall'SDK)

- **HTTPS obbligatorio** (http solo per `localhost/127.0.0.1/*.local/*.test`).
- **Validazione fail-fast**: E.164 normalizzato, messaggio 1–1600 char, `Idempotency-Key` ASCII stampabile (anti header-injection), credenziali senza whitespace/control-char.
- **TLS verify sempre attivo**, timeout/connect-timeout configurabili.
- **Nessun segreto nei log/eccezioni** (API key solo nell'header `x-api-key`).
- `deviceToken` della creazione device mostrato una volta: conservalo cifrato (es. `encrypt()` Laravel / vault).

## Sviluppo

```bash
composer install
vendor/bin/phpunit
```
