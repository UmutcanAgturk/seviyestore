<?php

declare(strict_types=1);

namespace Seviye\Depo\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Depo\Domain\StockTransferStatus;
use Seviye\Depo\Repository\WpdbStockTransferRepository;
use Seviye\Depo\Tests\Fakes\FakeConnection;

final class WpdbStockTransferRepositoryTest extends TestCase
{
    public function testFindReturnsNullWhenNoRowMatches(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbStockTransferRepository($connection);

        self::assertNull($repository->find(999));
    }

    public function testFindHydratesTheRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row()];
        $repository = new WpdbStockTransferRepository($connection);

        $transfer = $repository->find(4);

        self::assertNotNull($transfer);
        self::assertSame(500, $transfer->fromProductId);
        self::assertSame(600, $transfer->toProductId);
        self::assertSame(10, $transfer->quantity);
        self::assertNull($transfer->fromBranchId);
        self::assertSame(7, $transfer->toBranchId);
        self::assertSame(StockTransferStatus::PENDING, $transfer->status);
        self::assertNull($transfer->completedAt);
    }

    public function testFindHydratesCompletedAtOnlyWhenNotPending(): void
    {
        $connection = new FakeConnection();
        $row = $this->row();
        $row['status'] = 'completed';
        $row['completed_by'] = '9';
        $row['updated_at'] = '2026-08-07 12:00:00';
        $connection->resultsToReturn = [$row];
        $repository = new WpdbStockTransferRepository($connection);

        $transfer = $repository->find(4);

        self::assertNotNull($transfer);
        self::assertSame(StockTransferStatus::COMPLETED, $transfer->status);
        self::assertSame(9, $transfer->completedByUserId);
        self::assertSame('2026-08-07 12:00:00', $transfer->completedAt);
    }

    public function testAllReturnsEmptyListWhenNoTransfersMatch(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbStockTransferRepository($connection);

        self::assertSame([], $repository->all());
    }

    public function testAllFiltersByEitherSideWhenBranchIdIsAnInt(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbStockTransferRepository($connection);

        $repository->all(null, 7);

        self::assertCount(1, $connection->queriedSql);
        self::assertStringContainsString('from_branch_id = 7 OR to_branch_id = 7', $connection->queriedSql[0]);
    }

    public function testAllFiltersByEitherSideBeingNullWhenBranchIdIsHq(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbStockTransferRepository($connection);

        $repository->all(null, null);

        self::assertCount(1, $connection->queriedSql);
        self::assertStringContainsString('from_branch_id IS NULL OR to_branch_id IS NULL', $connection->queriedSql[0]);
    }

    public function testCreateInsertsAPendingRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row()];
        $repository = new WpdbStockTransferRepository($connection);

        $repository->create(500, 600, 10, null, 7, 'Sezon sonu fazlası', 3);

        self::assertCount(1, $connection->inserted);

        [$table, $data] = $connection->inserted[0];
        self::assertSame('test_scp_stock_transfers', $table);
        self::assertSame(500, $data['from_product_id']);
        self::assertSame(600, $data['to_product_id']);
        self::assertSame(10, $data['quantity']);
        self::assertNull($data['from_branch_id']);
        self::assertSame(7, $data['to_branch_id']);
        self::assertSame('pending', $data['status']);
        self::assertSame(3, $data['requested_by']);
        self::assertNull($data['completed_by']);
    }

    public function testCompleteRunsAnUpdateQuerySettingStatusAndCompletedBy(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbStockTransferRepository($connection);

        $repository->complete(4, 9);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('status = completed', $connection->queries[0]);
        self::assertStringContainsString('completed_by = 9', $connection->queries[0]);
        self::assertStringContainsString('WHERE id = 4', $connection->queries[0]);
    }

    public function testCancelRunsAnUpdateQuerySettingStatusToCancelled(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbStockTransferRepository($connection);

        $repository->cancel(4);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('status = cancelled', $connection->queries[0]);
        self::assertStringContainsString('WHERE id = 4', $connection->queries[0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'id' => '4',
            'from_product_id' => '500',
            'to_product_id' => '600',
            'quantity' => '10',
            'from_branch_id' => null,
            'to_branch_id' => '7',
            'status' => 'pending',
            'note' => 'Sezon sonu fazlası',
            'requested_by' => '3',
            'completed_by' => null,
            'created_at' => '2026-08-07 10:00:00',
            'updated_at' => '2026-08-07 10:00:00',
        ];
    }
}
