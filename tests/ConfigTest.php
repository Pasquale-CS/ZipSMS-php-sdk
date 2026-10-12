<?php

declare(strict_types=1);

namespace ZipSms\Tests;

use PHPUnit\Framework\TestCase;
use ZipSms\Config;
use ZipSms\Exceptions\ValidationException;

final class ConfigTest extends TestCase
{
    public function testHttpsEnforced(): void
    {
        $this->expectException(ValidationException::class);
        new Config(baseUrl: 'http://api.zipsms.it', apiKey: 'sk_test_1234567890');
    }

    public function testHttpAllowedOnLocalhost(): void
    {
        $c = new Config(baseUrl: 'http://localhost:3001', apiKey: 'sk_test_1234567890');
        $this->assertSame('http://localhost:3001', $c->baseUrl());
    }

    public function testRejectsShortKey(): void
    {
        $this->expectException(ValidationException::class);
        new Config(baseUrl: 'https://api.zipsms.it', apiKey: 'short');
    }
}
