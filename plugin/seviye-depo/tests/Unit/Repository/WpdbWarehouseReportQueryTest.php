<?php

declare(strict_types=1);

namespace Seviye\Depo\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Depo\Contracts\PurchaseOrderReportFilter;
use Seviye\Depo\Repository\WpdbWarehouseReportQuery;
use Seviye\Depo\Tests\Fakes\FakeConnection;

final class WpdbWarehouseReportQueryTest extends TestCase
{
    public function testSearchWithNoFilterQueriesEverything(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row()];
        $query = new WpdbWarehouseReportQuery($connection);

        $records = $query->search(new PurchaseOrderReportFilter());

        self::assertCount(1, $records);
        self::assertSame(3, $records[0]->supplierId);
        self::assertSame(910.0, $records[0]->totalCost);
    }

    public function testSearchWithSupplierAndDateRangeBuildsAWhereClause(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $query = new WpdbWarehouseReportQuery($connection);

        $query->search(new PurchaseOrderReportFilter(supplierId: 3, fromDate: '2026-07-01', toDate: '2026-07-31'));

        self::assertStringContainsString('po.supplier_id = 3', $connection->queriedSql[0]);
        self::assertStringContainsString('po.created_at >= 2026-07-01 00:00:00', $connection->queriedSql[0]);
        self::assertStringContainsString('po.created_at <= 2026-07-31 23:59:59', $connection->queriedSql[0]);
    }

    public function testHydrateOnlyExposesCompletedAtWhenStatusIsCompleted(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row('sent')];
        $query = new WpdbWarehouseReportQuery($connection);

        $records = $query->search(new PurchaseOrderReportFilter());

        self::assertSame('sent', $records[0]->status);
        self::assertNull($records[0]->completedAt);
    }

    public function testHydrateExposesCompletedAtWhenStatusIsCompleted(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row('completed')];
        $query = new WpdbWarehouseReportQuery($connection);

        $records = $query->search(new PurchaseOrderReportFilter());

        self::assertSame('2026-07-15 09:00:00', $records[0]->completedAt);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $status = 'completed'): array
    {
        return [
            'id' => '10',
            'supplier_id' => '3',
            'status' => $status,
            'expected_date' => '2026-07-10',
            'created_at' => '2026-07-01 08:00:00',
            'updated_at' => '2026-07-15 09:00:00',
            'total_cost' => '910.00',
        ];
    }
}
