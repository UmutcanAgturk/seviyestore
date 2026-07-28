<?php

declare(strict_types=1);

namespace Seviye\Branches\Tests\Unit\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Seviye\Branches\Domain\CommissionRate;

final class CommissionRateTest extends TestCase
{
    public function testAcceptsBoundaryValues(): void
    {
        self::assertSame(0.0, CommissionRate::fromPercentage(0.0)->percentage());
        self::assertSame(100.0, CommissionRate::fromPercentage(100.0)->percentage());
    }

    public function testRoundsToTwoDecimals(): void
    {
        self::assertSame(12.35, CommissionRate::fromPercentage(12.3456)->percentage());
    }

    public function testAsFractionDividesByOneHundred(): void
    {
        self::assertSame(0.125, CommissionRate::fromPercentage(12.5)->asFraction());
    }

    public function testRejectsNegativeValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CommissionRate::fromPercentage(-0.01);
    }

    public function testRejectsValuesAboveOneHundred(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CommissionRate::fromPercentage(100.01);
    }
}
