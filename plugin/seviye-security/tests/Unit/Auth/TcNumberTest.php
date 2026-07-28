<?php

declare(strict_types=1);

namespace Seviye\Security\Tests\Unit\Auth;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Seviye\Security\Auth\TcNumber;

final class TcNumberTest extends TestCase
{
    /**
     * @return list<array{0: string}>
     */
    public static function validNumbers(): array
    {
        return [
            ['10000000146'],
            ['11111111110'],
            ['19191919190'],
        ];
    }

    /**
     * @return list<array{0: string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'wrong checksum' => ['12345678901'],
            'starts with zero' => ['01234567890'],
            'too short' => ['1234567890'],
            'too long' => ['123456789012'],
            'non-numeric' => ['1234567890a'],
            'empty string' => [''],
            'all zeros' => ['00000000000'],
        ];
    }

    /**
     * @dataProvider validNumbers
     */
    public function testIsValidAcceptsAlgorithmicallyValidNumbers(string $number): void
    {
        self::assertTrue(TcNumber::isValid($number));
    }

    /**
     * @dataProvider invalidNumbers
     */
    public function testIsValidRejectsInvalidNumbers(string $number): void
    {
        self::assertFalse(TcNumber::isValid($number));
    }

    public function testFromStringReturnsTheSameValue(): void
    {
        $tcNumber = TcNumber::fromString('10000000146');

        self::assertSame('10000000146', $tcNumber->value());
        self::assertSame('10000000146', (string) $tcNumber);
    }

    public function testFromStringThrowsForAnInvalidNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TcNumber::fromString('12345678901');
    }
}
