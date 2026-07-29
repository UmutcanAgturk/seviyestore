<?php

declare(strict_types=1);

namespace Seviye\Commerce\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Commerce\Repository\WpdbOrderLineItemRepository;
use Seviye\Commerce\Tests\Fakes\FakeConnection;

final class WpdbOrderLineItemRepositoryTest extends TestCase
{
    public function testCreateInsertsAndReadsBackByLastInsertId(): void
    {
        $connection = new FakeConnection();
        $connection->nextInsertId = 9;
        $connection->resultsToReturn = [$this->row(9, 500, 3, 42, 7, '12.50', '89.90', 'processing')];
        $repository = new WpdbOrderLineItemRepository($connection);

        $item = $repository->create(500, 3, 42, 7, 12.5, 89.90, 'processing');

        self::assertSame(9, $item->id);
        self::assertSame(500, $item->orderId);
        self::assertSame(3, $item->orderItemId);
        self::assertSame(42, $item->studentId);
        self::assertSame(7, $item->branchId);
        self::assertSame(12.5, $item->commissionRate);
        self::assertSame(89.90, $item->price);
        self::assertSame('processing', $item->status);

        [$table, $data] = $connection->inserted[0];
        self::assertSame('test_scp_order_line_items', $table);
        self::assertSame(500, $data['order_id']);
        self::assertSame('processing', $data['status']);
    }

    public function testUpdateStatusForOrderIssuesAnUpdateQuery(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbOrderLineItemRepository($connection);

        $repository->updateStatusForOrder(500, 'completed');

        self::assertCount(1, $connection->queries);
    }

    public function testFindByOrderHydratesEveryRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [
            $this->row(1, 500, 3, 42, 7, '12.50', '89.90', 'processing'),
            $this->row(2, 500, 4, 43, 7, '12.50', '49.90', 'processing'),
        ];
        $repository = new WpdbOrderLineItemRepository($connection);

        $items = $repository->findByOrder(500);

        self::assertCount(2, $items);
        self::assertSame(42, $items[0]->studentId);
        self::assertSame(43, $items[1]->studentId);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        int $id,
        int $orderId,
        int $orderItemId,
        int $studentId,
        int $branchId,
        string $commissionRate,
        string $price,
        string $status
    ): array {
        return [
            'id' => (string) $id,
            'order_id' => (string) $orderId,
            'order_item_id' => (string) $orderItemId,
            'student_id' => (string) $studentId,
            'branch_id' => (string) $branchId,
            'commission_rate' => $commissionRate,
            'price' => $price,
            'status' => $status,
        ];
    }
}
