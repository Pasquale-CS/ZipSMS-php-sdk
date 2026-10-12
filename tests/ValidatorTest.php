<?php

declare(strict_types=1);

namespace ZipSms\Tests;

use PHPUnit\Framework\TestCase;
use ZipSms\Exceptions\ValidationException;
use ZipSms\Validation\Validator;

final class ValidatorTest extends TestCase
{
    public function testPhoneNormalization(): void
    {
        $this->assertSame('+393331234567', Validator::phone('+39 333 123 4567'));
        $this->assertSame('+393331234567', Validator::phone('+39-333-123-4567'));
    }

    public function testPhoneRejectsInvalid(): void
    {
        $this->expectException(ValidationException::class);
        Validator::phone('3331234567'); // manca +
    }

    public function testMessageLength(): void
    {
        $this->assertSame('ciao', Validator::message('  ciao  '));
        $this->expectException(ValidationException::class);
        Validator::message(str_repeat('x', 1601));
    }

    public function testIdempotencyKeyGeneratedWhenMissing(): void
    {
        $k = Validator::idempotencyKey(null);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $k
        );
    }

    public function testIdempotencyKeyRejectsCrlf(): void
    {
        $this->expectException(ValidationException::class);
        Validator::idempotencyKey("abc\r\nX-Injected: 1");
    }

    public function testDeviceNameStrict(): void
    {
        $this->assertSame('ufficio-1', Validator::deviceName('ufficio-1'));
        $this->expectException(ValidationException::class);
        Validator::deviceName('Ufficio 1!');
    }

    public function testDeviceTargetExactlyOne(): void
    {
        $this->assertSame(['deviceId' => 'dev-1'], Validator::deviceTarget('dev-1', null));
        $this->assertSame(['deviceName' => 'ufficio-1'], Validator::deviceTarget(null, 'ufficio-1'));

        try {
            Validator::deviceTarget('dev-1', 'ufficio-1');
            $this->fail('attesi entrambi → eccezione');
        } catch (ValidationException) {
            // ok
        }

        try {
            Validator::deviceTarget(null, null);
            $this->fail('attesi nessuno → eccezione');
        } catch (ValidationException) {
            // ok
        }
    }
}
