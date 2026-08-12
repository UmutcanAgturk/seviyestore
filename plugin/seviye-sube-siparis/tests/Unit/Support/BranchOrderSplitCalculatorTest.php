<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\SubeSiparis\Support\BranchOrderSplitCalculator;

final class BranchOrderSplitCalculatorTest extends TestCase
{
    public function testEntireRequestIsFreeWhenWithinRemainingQuota(): void
    {
        $calculator = new BranchOrderSplitCalculator();

        $result = $calculator->split(30, 100, 0);

        self::assertSame(['free' => 30, 'paid' => 0], $result);
    }

    public function testRequestIsSplitWhenItCrossesTheRemainingQuota(): void
    {
        $calculator = new BranchOrderSplitCalculator();

        $result = $calculator->split(30, 100, 80);

        self::assertSame(['free' => 20, 'paid' => 10], $result);
    }

    public function testEntireRequestIsPaidWhenQuotaAlreadyExhausted(): void
    {
        $calculator = new BranchOrderSplitCalculator();

        $result = $calculator->split(10, 100, 100);

        self::assertSame(['free' => 0, 'paid' => 10], $result);
    }

    public function testEntireRequestIsPaidWhenThereIsNoQuotaAtAll(): void
    {
        $calculator = new BranchOrderSplitCalculator();

        $result = $calculator->split(10, 0, 0);

        self::assertSame(['free' => 0, 'paid' => 10], $result);
    }

    public function testAlreadyConsumedNeverPushesFreeBelowZeroEvenIfItOverspentEarlier(): void
    {
        // Defensive: consumed > quota should not happen in practice, but the
        // calculator must not return a negative "free" count if it does.
        $calculator = new BranchOrderSplitCalculator();

        $result = $calculator->split(10, 50, 60);

        self::assertSame(['free' => 0, 'paid' => 10], $result);
    }
}
