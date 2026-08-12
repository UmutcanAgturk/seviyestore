<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use Seviye\SubeSiparis\Domain\BranchOrderItem;

final class BranchOrderItemTest extends TestCase
{
    public function testHasPaidPortionIsFalseWhenEntirelyFree(): void
    {
        $item = new BranchOrderItem(1, 10, 500, 20, 20, 0, null);

        self::assertFalse($item->hasPaidPortion());
        self::assertSame(0.0, $item->paidAmount());
    }

    public function testPaidAmountMultipliesPaidQuantityByUnitPrice(): void
    {
        $item = new BranchOrderItem(1, 10, 500, 30, 20, 10, 49.9);

        self::assertTrue($item->hasPaidPortion());
        self::assertSame(499.0, $item->paidAmount());
    }

    public function testPaidAmountIsZeroWhenUnitPriceNotYetResolved(): void
    {
        $item = new BranchOrderItem(1, 10, 500, 20, 0, 0, null);

        self::assertSame(0.0, $item->paidAmount());
    }
}
