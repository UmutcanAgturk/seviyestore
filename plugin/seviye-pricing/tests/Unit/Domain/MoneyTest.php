<?php

declare(strict_types=1);

namespace Seviye\Pricing\Tests\Unit\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Seviye\Pricing\Domain\Money;

final class MoneyTest extends TestCase
{
    public function testFromFloatRoundsToTwoDecimals(): void
    {
        $money = Money::fromFloat(19.999);

        self::assertSame(20.0, $money->toFloat());
    }

    public function testZeroIsAllowed(): void
    {
        self::assertSame(0.0, Money::fromFloat(0.0)->toFloat());
    }

    public function testNegativeAmountIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromFloat(-1.0);
    }

    public function testNonFiniteAmountIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromFloat(NAN);
    }
}
