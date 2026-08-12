<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Seviye\SubeSiparis\Repository\WpdbBranchOrderRepository;
use Seviye\SubeSiparis\Tests\Fakes\FakeConnection;

final class WpdbBranchOrderRepositoryTest extends TestCase
{
    public function testFindItemReturnsNullWhenNoRowMatches(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbBranchOrderRepository($connection);

        self::assertNull($repository->findItem(999));
    }

    public function testFindItemHydratesTheRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [[
            'id' => '4',
            'branch_order_id' => '10',
            'product_id' => '500',
            'quantity_requested' => '30',
            'free_quantity_applied' => '20',
            'paid_quantity' => '10',
            'unit_price' => '49.90',
        ]];
        $repository = new WpdbBranchOrderRepository($connection);

        $item = $repository->findItem(4);

        self::assertNotNull($item);
        self::assertSame(4, $item->id);
        self::assertSame(30, $item->quantityRequested);
        self::assertSame(20, $item->freeQuantityApplied);
        self::assertSame(10, $item->paidQuantity);
        self::assertSame(49.90, $item->unitPrice);
    }

    public function testAllReturnsEmptyListWhenNoOrdersMatch(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbBranchOrderRepository($connection);

        self::assertSame([], $repository->all());
    }

    public function testSubmitRunsAnUpdateQuerySettingStatusToSubmitted(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbBranchOrderRepository($connection);

        $repository->submit(4);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('status = submitted', $connection->queries[0]);
        self::assertStringContainsString('WHERE id = 4', $connection->queries[0]);
    }

    public function testCancelRunsAnUpdateQuerySettingStatusToCancelled(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbBranchOrderRepository($connection);

        $repository->cancel(4);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('status = cancelled', $connection->queries[0]);
    }

    public function testRejectRunsAnUpdateQueryWithTheReason(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbBranchOrderRepository($connection);

        $repository->reject(4, 'Bütçe yetersiz');

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('status = rejected', $connection->queries[0]);
        self::assertStringContainsString('Bütçe yetersiz', $connection->queries[0]);
    }

    /**
     * Same "fake connection returns a fixed result for every getResults()
     * call" limitation as WpdbPurchaseOrderRepositoryTest - create()'s final
     * readback throws, but every insert() call up to that point is still
     * fully assertable.
     */
    public function testCreateInsertsHeaderAndEveryItemRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbBranchOrderRepository($connection);

        try {
            $repository->create(3, 7, 'Kayıt döneminde forma ihtiyacı', [
                ['product_id' => 500, 'quantity_requested' => 30],
                ['product_id' => 501, 'quantity_requested' => 5],
            ]);
            self::fail('Expected a RuntimeException from the unreadable final find().');
        } catch (RuntimeException) {
            // Expected - see method docblock.
        }

        self::assertCount(3, $connection->inserted);

        [$headerTable, $headerData] = $connection->inserted[0];
        self::assertSame('test_scp_branch_orders', $headerTable);
        self::assertSame(3, $headerData['branch_id']);
        self::assertSame('draft', $headerData['status']);

        [$item1Table, $item1Data] = $connection->inserted[1];
        self::assertSame('test_scp_branch_order_items', $item1Table);
        self::assertSame(500, $item1Data['product_id']);
        self::assertSame(30, $item1Data['quantity_requested']);
        self::assertSame(0, $item1Data['free_quantity_applied']);
        self::assertSame(0, $item1Data['paid_quantity']);
        self::assertNull($item1Data['unit_price']);

        [, $item2Data] = $connection->inserted[2];
        self::assertSame(501, $item2Data['product_id']);
        self::assertSame(5, $item2Data['quantity_requested']);
    }

    public function testApproveWritesEachItemsSplitAndMovesToAwaitingPaymentWhenAnyPortionIsPaid(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbBranchOrderRepository($connection);

        try {
            $repository->approve(4, 1, [
                10 => ['free' => 20, 'paid' => 0, 'unit_price' => null],
                11 => ['free' => 0, 'paid' => 5, 'unit_price' => 49.9],
            ]);
            self::fail('Expected a RuntimeException from the unreadable final find().');
        } catch (RuntimeException) {
            // Expected - final find() readback, same limitation as above.
        }

        // Two item UPDATEs + one header UPDATE.
        self::assertCount(3, $connection->queries);
        self::assertStringContainsString('WHERE id = 10', $connection->queries[0]);
        self::assertStringContainsString('WHERE id = 11', $connection->queries[1]);
        self::assertStringContainsString('status = awaiting_payment', $connection->queries[2]);
        self::assertStringContainsString('approved_by = 1', $connection->queries[2]);
    }

    public function testApproveMovesStraightToCompletedWhenEverythingIsFree(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbBranchOrderRepository($connection);

        try {
            $repository->approve(4, 1, [
                10 => ['free' => 20, 'paid' => 0, 'unit_price' => null],
            ]);
            self::fail('Expected a RuntimeException from the unreadable final find().');
        } catch (RuntimeException) {
            // Expected.
        }

        self::assertCount(2, $connection->queries);
        self::assertStringContainsString('status = completed', $connection->queries[1]);
    }

    public function testConsumedFreeQuantityQueriesOnlyLockedInStatuses(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['total' => '45']];
        $repository = new WpdbBranchOrderRepository($connection);

        $consumed = $repository->consumedFreeQuantity(3, 500);

        self::assertSame(45, $consumed);
        self::assertStringContainsString('awaiting_payment', $connection->queriedSql[0]);
        self::assertStringContainsString('completed', $connection->queriedSql[0]);
    }

    public function testConsumedFreeQuantityDefaultsToZeroWhenNoRowsMatch(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbBranchOrderRepository($connection);

        self::assertSame(0, $repository->consumedFreeQuantity(3, 500));
    }
}
