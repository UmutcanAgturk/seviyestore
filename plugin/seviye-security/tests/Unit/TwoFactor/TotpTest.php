<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\TwoFactor;

use PHPUnit\Framework\TestCase;
use Seviye\Security\TwoFactor\Base32;
use Seviye\Security\TwoFactor\Totp;

/**
 * Verified against RFC 6238 Appendix B's official SHA1 test vectors (which
 * publish 8-digit codes; this platform uses 6 digits, so each expected
 * value here is the vector's last 6 digits - see the RFC's own truncation
 * formula, mod 10^Digits).
 */
final class TotpTest extends TestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // Base32::encode('12345678901234567890')

    public function testGenerateMatchesRfc6238Vectors(): void
    {
        self::assertSame('287082', Totp::generate(self::SECRET, 59));
        self::assertSame('081804', Totp::generate(self::SECRET, 1111111109));
        self::assertSame('050471', Totp::generate(self::SECRET, 1111111111));
        self::assertSame('005924', Totp::generate(self::SECRET, 1234567890));
        self::assertSame('279037', Totp::generate(self::SECRET, 2000000000));
    }

    public function testVerifyAcceptsTheCurrentCode(): void
    {
        self::assertTrue(Totp::verify(self::SECRET, '287082', 1, 59));
    }

    public function testVerifyRejectsAWrongCode(): void
    {
        self::assertFalse(Totp::verify(self::SECRET, '000000', 1, 59));
    }

    public function testVerifyToleratesOneStepOfClockDrift(): void
    {
        // t=59 -> step 1; step 2 covers t in [60, 89].
        $nextStepCode = Totp::generate(self::SECRET, 65);

        self::assertTrue(Totp::verify(self::SECRET, $nextStepCode, 1, 59));
    }

    public function testVerifyRejectsCodeOutsideTheDriftWindow(): void
    {
        // Three steps ahead (90s) is outside the default window of 1.
        $farFutureCode = Totp::generate(self::SECRET, 149);

        self::assertFalse(Totp::verify(self::SECRET, $farFutureCode, 1, 59));
    }

    public function testGenerateAlwaysProducesSixDigits(): void
    {
        self::assertSame(6, strlen(Totp::generate(self::SECRET, 1)));
    }
}
