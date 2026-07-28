<?php

declare(strict_types=1);

namespace Seviye\Branches\Tests\Unit\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Seviye\Branches\Domain\Iban;

final class IbanTest extends TestCase
{
    /**
     * @return list<array{0: string}>
     */
    public static function validIbans(): array
    {
        return [
            ['TR330006100519786457841326'],
            ['GB82 WEST 1234 5698 7654 32'],
            ['DE89370400440532013000'],
        ];
    }

    /**
     * @return list<array{0: string}>
     */
    public static function invalidIbans(): array
    {
        return [
            'wrong checksum' => ['TR330006100519786457841327'],
            'too short' => ['TR33'],
            'lowercase country code rejected by structure, not case' => ['xx000000000000000000'],
            'empty' => [''],
        ];
    }

    /**
     * @dataProvider validIbans
     */
    public function testIsValidAcceptsWellKnownValidIbans(string $iban): void
    {
        self::assertTrue(Iban::isValid($iban));
    }

    /**
     * @dataProvider invalidIbans
     */
    public function testIsValidRejectsInvalidIbans(string $iban): void
    {
        self::assertFalse(Iban::isValid($iban));
    }

    public function testFromStringNormalizesSpacesAndCase(): void
    {
        $iban = Iban::fromString('gb82 west 1234 5698 7654 32');

        self::assertSame('GB82WEST12345698765432', $iban->value());
        self::assertSame('GB82WEST12345698765432', (string) $iban);
    }

    public function testFromStringThrowsForAnInvalidIban(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Iban::fromString('not-an-iban');
    }
}
