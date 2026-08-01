<?php

declare(strict_types=1);

namespace Seviye\Depo\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Depo\Domain\PurchaseOrderItem;
use Seviye\Depo\Domain\PurchaseOrderStatus;
use Seviye\Depo\Support\PurchaseOrderStatusCalculator;

final class PurchaseOrderStatusCalculatorTest extends TestCase
{
    public function testDraftStaysUntouchedRegardlessOfItems(): void
    {
        $calculator = new PurchaseOrderStatusCalculator();
        $items = [new PurchaseOrderItem(1, 10, 500, 20, 20, null)];

        $result = $calculator->recalculate(PurchaseOrderStatus::DRAFT, $items);

        self::assertSame(PurchaseOrderStatus::DRAFT, $result);
    }

    public function testCancelledStaysUntouchedEvenIfEverythingWasReceived(): void
    {
        $calculator = new PurchaseOrderStatusCalculator();
        $items = [new PurchaseOrderItem(1, 10, 500, 20, 20, null)];

        $result = $calculator->recalculate(PurchaseOrderStatus::CANCELLED, $items);

        self::assertSame(PurchaseOrderStatus::CANCELLED, $result);
    }

    public function testSentWithNothingReceivedStaysSent(): void
    {
        $calculator = new PurchaseOrderStatusCalculator();
        $items = [new PurchaseOrderItem(1, 10, 500, 20, 0, null)];

        $result = $calculator->recalculate(PurchaseOrderStatus::SENT, $items);

        self::assertSame(PurchaseOrderStatus::SENT, $result);
    }

    public function testSentBecomesPartiallyReceivedWhenSomeButNotAllItemsAreReceived(): void
    {
        $calculator = new PurchaseOrderStatusCalculator();
        $items = [
            new PurchaseOrderItem(1, 10, 500, 20, 20, null),
            new PurchaseOrderItem(2, 10, 501, 5, 2, null),
        ];

        $result = $calculator->recalculate(PurchaseOrderStatus::SENT, $items);

        self::assertSame(PurchaseOrderStatus::PARTIALLY_RECEIVED, $result);
    }

    public function testPartiallyReceivedBecomesCompletedOnceEveryItemIsFullyReceived(): void
    {
        $calculator = new PurchaseOrderStatusCalculator();
        $items = [
            new PurchaseOrderItem(1, 10, 500, 20, 20, null),
            new PurchaseOrderItem(2, 10, 501, 5, 5, null),
        ];

        $result = $calculator->recalculate(PurchaseOrderStatus::PARTIALLY_RECEIVED, $items);

        self::assertSame(PurchaseOrderStatus::COMPLETED, $result);
    }

    public function testOverReceivingAnItemStillCountsAsFullyReceived(): void
    {
        $calculator = new PurchaseOrderStatusCalculator();
        $items = [new PurchaseOrderItem(1, 10, 500, 20, 25, null)];

        $result = $calculator->recalculate(PurchaseOrderStatus::SENT, $items);

        self::assertSame(PurchaseOrderStatus::COMPLETED, $result);
    }

    public function testEmptyItemListLeavesStatusUnchanged(): void
    {
        $calculator = new PurchaseOrderStatusCalculator();

        $result = $calculator->recalculate(PurchaseOrderStatus::SENT, []);

        self::assertSame(PurchaseOrderStatus::SENT, $result);
    }
}
