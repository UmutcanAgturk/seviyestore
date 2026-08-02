<?php

declare(strict_types=1);

namespace Seviye\Reports\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Reports\Support\DailyTrendBuilder;

final class DailyTrendBuilderTest extends TestCase
{
    public function testZeroFillsEveryDayInTheRange(): void
    {
        $points = (new DailyTrendBuilder())->build([], '2026-01-01', '2026-01-03');

        self::assertSame(
            [
                ['date' => '2026-01-01', 'order_count' => 0, 'total' => 0.0],
                ['date' => '2026-01-02', 'order_count' => 0, 'total' => 0.0],
                ['date' => '2026-01-03', 'order_count' => 0, 'total' => 0.0],
            ],
            $points
        );
    }

    public function testGroupsRecordsByDateAndCountsDistinctOrders(): void
    {
        $records = [
            ['order_id' => 1, 'date' => '2026-01-01', 'line_total' => 100.0],
            // Same order, second line item - must count as ONE order, not two.
            ['order_id' => 1, 'date' => '2026-01-01', 'line_total' => 50.0],
            ['order_id' => 2, 'date' => '2026-01-02', 'line_total' => 75.5],
        ];

        $points = (new DailyTrendBuilder())->build($records, '2026-01-01', '2026-01-02');

        self::assertSame(
            [
                ['date' => '2026-01-01', 'order_count' => 1, 'total' => 150.0],
                ['date' => '2026-01-02', 'order_count' => 1, 'total' => 75.5],
            ],
            $points
        );
    }

    public function testIgnoresRecordsOutsideTheRequestedRange(): void
    {
        $records = [
            ['order_id' => 1, 'date' => '2025-12-31', 'line_total' => 999.0],
        ];

        $points = (new DailyTrendBuilder())->build($records, '2026-01-01', '2026-01-01');

        self::assertSame([['date' => '2026-01-01', 'order_count' => 0, 'total' => 0.0]], $points);
    }
}
