<?php

declare(strict_types=1);

namespace ZipSms\Resources;

use ZipSms\Http\HttpClient;
use ZipSms\Validation\Validator;

/**
 * Gestione device (auth: sessione Bearer).
 * Il deviceToken emesso alla creazione va conservato cifrato lato server.
 */
final class Devices
{
    public function __construct(private readonly HttpClient $http) {}

    /** Crea un device (emette deviceToken una sola volta). */
    public function create(string $name, string $fcmToken, array $extra = []): array
    {
        $payload = [
            'name' => Validator::deviceName($name),
            'fcmToken' => Validator::fcmToken($fcmToken),
        ];
        foreach (['deviceId', 'androidVersion', 'appVersion'] as $k) {
            if (isset($extra[$k]) && \is_string($extra[$k]) && trim($extra[$k]) !== '') {
                $payload[$k] = mb_substr(trim($extra[$k]), 0, 128);
            }
        }

        return $this->http->request('POST', '/api/v1/devices', ['json' => $payload]);
    }

    /** Elenca i propri device (con stato online). */
    public function list(): array
    {
        return $this->http->request('GET', '/api/v1/devices');
    }

    /** Riprende un device dopo reinstallazione. */
    public function resume(string $deviceId, string $fcmToken): array
    {
        return $this->http->request('POST', '/api/v1/devices/resume', [
            'json' => [
                'deviceId' => Validator::deviceId($deviceId),
                'fcmToken' => Validator::fcmToken($fcmToken),
            ],
        ]);
    }

    /** Collega un device orfano al proprio account. */
    public function claim(string $id): array
    {
        return $this->http->request('POST', '/api/v1/devices/' . rawurlencode(Validator::deviceId($id)) . '/claim');
    }

    /** Rinomina un device. */
    public function rename(string $id, string $name): array
    {
        return $this->http->request('PATCH', '/api/v1/devices/' . rawurlencode(Validator::deviceId($id)), [
            'json' => ['name' => Validator::deviceName($name)],
        ]);
    }

    /** Revoca un device (soft, mantiene lo storico). */
    public function remove(string $id): void
    {
        $this->http->request('DELETE', '/api/v1/devices/' . rawurlencode(Validator::deviceId($id)));
    }

    /**
     * Aggiorna il token FCM (auth: deviceToken — passare il token via bearer).
     * Usare un client istanziato con withBearerToken($deviceToken).
     */
    public function updateFcmToken(string $id, string $fcmToken): array
    {
        return $this->http->request('POST', '/api/v1/devices/' . rawurlencode(Validator::deviceId($id)) . '/fcm-token', [
            'json' => ['fcmToken' => Validator::fcmToken($fcmToken)],
        ]);
    }
}
