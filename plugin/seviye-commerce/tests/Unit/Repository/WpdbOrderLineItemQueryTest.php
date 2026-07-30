<?php

declare(strict_types=1);

namespace Seviye\Commerce\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Commerce\Contracts\OrderLineItemFilter;
use Seviye\Commerce\Repository\WpdbOrderLineItemQuery;
use Seviye\Commerce\Tests\Fakes\FakeConnection;

final class WpdbOrderLineItemQueryTest extends TestCase
{
    public function testSearchWithNoFilterQueriesEverything(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row()];
        $query = new WpdbOrderLineItemQuery($connection);

        $records = $query->search(new OrderLineItemFilter());

        self::assertCount(1, $records);
        self::assertSame(55, $records[0]->productId);
        self::assertSame('2026-07-30 10:00:00', $records[0]->createdAt);
    }

    public function testSearchWithBranchAndProductFilterBuildsAWhereClause(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row()];
        $query = new WpdbOrderLineItemQuery($connection);

        $query->search(new OrderLineItemFilter(branchId: 7, productId: 55));

        self::assertStringContainsString('WHERE branch_id = 7 AND product_id = 55', $connection->queriedSql[0]);
    }

    public function testSearchWithDateRangeUsesInclusiveDayBoundaries(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $query = new WpdbOrderLineItemQuery($connection);

        $query->search(new OrderLineItemFilter(fromDate: '2026-07-01', toDate: '2026-07-31'));

        self::assertStringContainsString('created_at >= 2026-07-01 00:00:00', $connection->queriedSql[0]);
        self::assertStringContainsString('created_at <= 2026-07-31 23:59:59', $connection->queriedSql[0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'id' => '1',
            'order_id' => '500',
            'order_item_id' => '3',
            'student_id' => '42',
            'branch_id' => '7',
            'product_id' => '55',
            'commission_rate' => '12.50',
            'price' => '89.90',
            'vat_amount' => '16.18',
            'status' => 'completed',
            'created_at' => '2026-07-30 10:00:00',
        ];
    }
}
