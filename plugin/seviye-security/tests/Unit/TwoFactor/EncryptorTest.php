<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\TwoFactor;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Seviye\Security\TwoFactor\Encryptor;

final class EncryptorTest extends TestCase
{
    public function testDecryptReversesEncrypt(): void
    {
        $encryptor = Encryptor::fromSecret('test-secret-does-not-need-fixed-length');

        $encrypted = $encryptor->encrypt('GEZDGNBVGY3TQOJQ');

        self::assertSame('GEZDGNBVGY3TQOJQ', $encryptor->decrypt($encrypted));
    }

    public function testEncryptedValueDoesNotContainThePlaintext(): void
    {
        $encryptor = Encryptor::fromSecret('test-secret');

        self::assertStringNotContainsString('GEZDGNBVGY3TQOJQ', $encryptor->encrypt('GEZDGNBVGY3TQOJQ'));
    }

    public function testEncryptingTheSamePlaintextTwiceProducesDifferentCiphertext(): void
    {
        $encryptor = Encryptor::fromSecret('test-secret');

        self::assertNotSame($encryptor->encrypt('same-value'), $encryptor->encrypt('same-value'));
    }

    public function testDecryptReturnsNullForGarbageInput(): void
    {
        $encryptor = Encryptor::fromSecret('test-secret');

        self::assertNull($encryptor->decrypt('not-valid-base64-ciphertext'));
    }

    public function testDecryptReturnsNullWhenKeyDoesNotMatch(): void
    {
        $encrypted = Encryptor::fromSecret('key-one')->encrypt('secret-value');

        self::assertNull(Encryptor::fromSecret('key-two')->decrypt($encrypted));
    }

    public function testConstructorRejectsAKeyOfTheWrongLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Encryptor('too-short');
    }
}
