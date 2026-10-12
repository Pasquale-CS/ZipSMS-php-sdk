<?php

declare(strict_types=1);

namespace ZipSms\Resources;

use ZipSms\Http\HttpClient;
use ZipSms\Validation\Validator;

/**
 * Invio e consultazione SMS.
 *
 * Auth:
 * - send/get/list → API key (x-api-key) oppure sessione Bearer.
 * - sendFromDashboard → solo sessione Bearer (dashboard manuale).
 */
final class Sms
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Accoda un SMS (R1). Idempotente via Idempotency-Key.
     *
     * @return array{id?:string,status?:string,segments?:int,...} es. ['id'=>..., 'status'=>'QUEUED', 'segments'=>1]
     */
    public function send(
        string $deviceId,
        string $to,
        string $message,
        ?string $reference = null,
        ?string $idempotencyKey = null,
    ): array {
        $payload = [
            'deviceId' => Validator::deviceId($deviceId),
            'to' => Validator::phone($to),
            'message' => Validator::message($message),
        ];
        $ref = Validator::reference($reference);
        if ($ref !== null) {
            $payload['reference'] = $ref;
        }

        return $this->http->request('POST', '/api/v1/sms/send', [
            'json' => $payload,
            'headers' => ['Idempotency-Key' => Validator::idempotencyKey($idempotencyKey)],
        ]);
    }

    /**
     * Invia e attende l'esito finale via polling (SENT/DELIVERED/FAILED).
     * Comodo per script sincroni; in produzione preferire webhook/polling dedicato.
     *
     * @throws \ZipSms\Exceptions\ZipSmsException allo scadere del timeout
     */
    public function sendAndWait(
        string $deviceId,
        string $to,
        string $message,
        ?string $reference = null,
        ?string $idempotencyKey = null,
        int $timeoutSeconds = 120,
        int $pollIntervalSeconds = 3,
    ): array {
        $sent = $this->send($deviceId, $to, $message, $reference, $idempotencyKey);
        $id = $sent['id'] ?? null;
        if (!\is_string($id) || $id === '') {
            return $sent; // risposta inattesa: la restituiamo così com'è
        }
        $deadline = time() + max(5, $timeoutSeconds);
        do {
            sleep(max(1, $pollIntervalSeconds));
            $current = $this->get($id);
            $status = strtoupper((string) ($current['status'] ?? ''));
            if (\in_array($status, ['SENT', 'DELIVERED', 'FAILED'], true)) {
                return $current;
            }
        } while (time() < $deadline);

        return $this->get($id);
    }

    /** Dettaglio + stato di un SMS (polling). */
    public function get(string $id): array
    {
        $smsId = Validator::smsId($id);
        return $this->http->request('GET', '/api/v1/sms/' . rawurlencode($smsId));
    }

    /**
     * Elenca i propri SMS (filtri tutti opzionali).
     *
     * @param array{status?:string,deviceId?:string,source?:string,from?:string,to?:string,page?:int,limit?:int} $filters
     */
    public function list(array $filters = []): array
    {
        $query = [];
        foreach (['status', 'deviceId', 'source', 'from', 'to'] as $k) {
            if (isset($filters[$k]) && \is_string($filters[$k]) && trim($filters[$k]) !== '') {
                $query[$k] = trim($filters[$k]);
            }
        }
        if (isset($filters['page'])) {
            $query['page'] = max(1, (int) $filters['page']);
        }
        if (isset($filters['limit'])) {
            $query['limit'] = min(100, max(1, (int) $filters['limit']));
        }

        return $this->http->request('GET', '/api/v1/sms', ['query' => $query]);
    }

    /**
     * Invio manuale multi-device dalla dashboard (auth: Bearer).
     *
     * @param string[] $deviceIds
     * @return array{results?:array,...} esito per device
     */
    public function sendFromDashboard(
        array $deviceIds,
        string $to,
        string $message,
        ?string $reference = null,
    ): array {
        $payload = [
            'deviceIds' => Validator::deviceIds($deviceIds),
            'to' => Validator::phone($to),
            'message' => Validator::message($message),
        ];
        $ref = Validator::reference($reference);
        if ($ref !== null) {
            $payload['reference'] = $ref;
        }

        return $this->http->request('POST', '/api/v1/sms/send-from-dashboard', ['json' => $payload]);
    }
}
