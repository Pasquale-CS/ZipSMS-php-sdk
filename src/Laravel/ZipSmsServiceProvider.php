<?php

declare(strict_types=1);

namespace ZipSms\Laravel;

use Illuminate\Support\ServiceProvider;
use ZipSms\Config;
use ZipSms\ZipSmsClient;

class ZipSmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/zipsms.php', 'zipsms');

        $this->app->singleton(ZipSmsClient::class, function ($app): ZipSmsClient {
            /** @var array<string,mixed> $cfg */
            $cfg = $app['config']->get('zipsms', []);

            $config = Config::create([
                'api_key' => $cfg['api_key'] ?? null,
                'bearer_token' => $cfg['session_token'] ?? null,
                'base_url' => $cfg['base_url'] ?? 'https://zipsms-gateway.pcs.website',
                'timeout' => $cfg['timeout'] ?? 15,
                'connect_timeout' => $cfg['connect_timeout'] ?? 5,
            ]);

            return new ZipSmsClient($config);
        });

        $this->app->alias(ZipSmsClient::class, 'zipsms');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/zipsms.php' => config_path('zipsms.php'),
            ], 'zipsms-config');
        }

        // Fail-fast: se mancano entrambe le credenziali, lo si scopre al boot
        // solo quando il client viene risolto — nessuna eccezione qui per
        // non rompere `artisan` su env senza chiavi.
    }
}
