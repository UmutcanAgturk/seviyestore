<?php

declare(strict_types=1);

namespace Seviye\Finance\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Finance\Repository\WpdbHakedisTotals;
use Seviye\Finance\Tests\Fakes\FakeConnection;

final class WpdbHakedisTotalsTest extends TestCase
{
    public function testSubtractsTotalSettledFromTotalAccrued(): void
    {
        $connection = new FakeConnection();
        $connection->resultsQueue = [
            [['total' => '1500.00']],
            [['total' => '400.00']],
        ];
        $totals = new WpdbHakedisTotals($connection);

        self::assertSame(1100.0, $totals->totalOutstandingBalance());
    }

    public function testTreatsNoRowsAsZeroOnBothSides(): void
    {
        $connection = new FakeConnection();
        $connection->resultsQueue = [[], []];
        $totals = new WpdbHakedisTotals($connection);

        self::assertSame(0.0, $totals->totalOutstandingBalance());
    }

    public function testTreatsANullSumAsZero(): void
    {
        $connection = new FakeConnection();
        $connection->resultsQueue = [
            [['total' => null]],
            [['total' => '100.00']],
        ];
        $totals = new WpdbHakedisTotals($connection);

        self::assertSame(-100.0, $totals->totalOutstandingBalance());
    }
}
