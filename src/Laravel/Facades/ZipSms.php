<?php

declare(strict_types=1);

namespace ZipSms\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use ZipSms\Resources\App;
use ZipSms\Resources\Devices;
use ZipSms\Resources\Sms;
use ZipSms\Resources\Usage;

/**
 * @method static Sms sms()
 * @method static Devices devices()
 * @method static Usage usage()
 * @method static App app()
 * @method static array send(string $to, string $message, ?string $deviceId = null, ?string $deviceName = null, ?string $reference = null, ?string $idempotencyKey = null)
 *
 * @see \ZipSms\ZipSmsClient
 */
class ZipSms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'zipsms';
    }
}
