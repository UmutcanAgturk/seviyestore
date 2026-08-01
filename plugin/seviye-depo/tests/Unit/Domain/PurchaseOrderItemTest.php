<?php

declare(strict_types=1);

namespace Seviye\Depo\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use Seviye\Depo\Domain\PurchaseOrderItem;

final class PurchaseOrderItemTest extends TestCase
{
    public function testRemainingQuantityIsOrderedMinusReceived(): void
    {
        $item = new PurchaseOrderItem(1, 10, 500, 20, 12, null);

        self::assertSame(8, $item->remainingQuantity());
        self::assertFalse($item->isFullyReceived());
    }

    public function testRemainingQuantityNeverGoesNegativeOnOverReceipt(): void
    {
        $item = new PurchaseOrderItem(1, 10, 500, 20, 25, null);

        self::assertSame(0, $item->remainingQuantity());
        self::assertTrue($item->isFullyReceived());
    }

    public function testIsFullyReceivedWhenQuantitiesMatchExactly(): void
    {
        $item = new PurchaseOrderItem(1, 10, 500, 20, 20, null);

        self::assertTrue($item->isFullyReceived());
    }
}
