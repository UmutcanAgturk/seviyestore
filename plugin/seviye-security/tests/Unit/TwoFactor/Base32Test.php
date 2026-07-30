<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\TwoFactor;

use PHPUnit\Framework\TestCase;
use Seviye\Security\TwoFactor\Base32;

final class Base32Test extends TestCase
{
    public function testEncodeMatchesKnownVector(): void
    {
        self::assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', Base32::encode('12345678901234567890'));
    }

    public function testDecodeReversesEncode(): void
    {
        $binary = random_bytes(20);

        self::assertSame($binary, Base32::decode(Base32::encode($binary)));
    }

    public function testDecodeIgnoresPaddingAndLowercase(): void
    {
        self::assertSame(
            Base32::decode('GEZDGNBVGY3TQOJQ'),
            Base32::decode('gezdgnbvgy3tqojq======')
        );
    }

    public function testRandomSecretIsTwentyBytesOfEntropy(): void
    {
        $secret = Base32::randomSecret();

        self::assertSame(20, strlen(Base32::decode($secret)));
        self::assertNotSame(Base32::randomSecret(), $secret);
    }
}
