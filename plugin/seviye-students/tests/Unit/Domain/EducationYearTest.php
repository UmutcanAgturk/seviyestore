<?php

declare(strict_types=1);

namespace Seviye\Students\Tests\Unit\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Seviye\Students\Domain\EducationYear;

final class EducationYearTest extends TestCase
{
    /**
     * @return list<array{0: string}>
     */
    public static function validYears(): array
    {
        return [
            ['2025-2026'],
            ['2000-2001'],
            ['2099-2100'],
        ];
    }

    /**
     * @return list<array{0: string}>
     */
    public static function invalidYears(): array
    {
        return [
            'non-consecutive' => ['2025-2027'],
            'reversed' => ['2026-2025'],
            'wrong format' => ['2025/2026'],
            'too short' => ['25-26'],
            'before minimum' => ['1999-2000'],
            'empty' => [''],
        ];
    }

    /**
     * @dataProvider validYears
     */
    public function testIsValidAcceptsConsecutiveYearPairs(string $year): void
    {
        self::assertTrue(EducationYear::isValid($year));
    }

    /**
     * @dataProvider invalidYears
     */
    public function testIsValidRejectsInvalidYears(string $year): void
    {
        self::assertFalse(EducationYear::isValid($year));
    }

    public function testStartYearReturnsTheFirstYearAsAnInteger(): void
    {
        self::assertSame(2025, EducationYear::fromString('2025-2026')->startYear());
    }

    public function testFromStringThrowsForAnInvalidYear(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EducationYear::fromString('not-a-year');
    }
}
