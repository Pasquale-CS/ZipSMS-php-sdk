<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZipSMS Gateway
    |--------------------------------------------------------------------------
    | api_key: chiave segreta per l'invio (header x-api-key).
    | session_token: JWT utente per dashboard (devices/usage/invio manuale).
    | base_url: deve essere HTTPS in produzione.
    |
    | Sicurezza: NON committare mai le chiavi. Usa .env + config:cache.
    */
    'api_key' => env('ZIPSMS_API_KEY'),
    'session_token' => env('ZIPSMS_SESSION_TOKEN'),
    'base_url' => env('ZIPSMS_BASE_URL', 'https://zipsms-gateway.pcs.website'),
    'timeout' => (float) env('ZIPSMS_TIMEOUT', 15),
    'connect_timeout' => (float) env('ZIPSMS_CONNECT_TIMEOUT', 5),
];
