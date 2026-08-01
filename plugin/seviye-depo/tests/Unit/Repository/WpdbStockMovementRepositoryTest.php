<?php

declare(strict_types=1);

namespace Seviye\Depo\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Depo\Domain\StockMovementType;
use Seviye\Depo\Repository\WpdbStockMovementRepository;
use Seviye\Depo\Tests\Fakes\FakeConnection;

final class WpdbStockMovementRepositoryTest extends TestCase
{
    public function testRecordInsertsAndReadsBackByLastInsertId(): void
    {
        $connection = new FakeConnection();
        $connection->nextInsertId = 9;
        $connection->resultsToReturn = [$this->row(9, 500, 'purchase_in', 20, 'purchase_order', 3)];
        $repository = new WpdbStockMovementRepository($connection);

        $movement = $repository->record(500, StockMovementType::PURCHASE_IN, 20, 'purchase_order', 3, 'Mal kabul', 7);

        self::assertSame(9, $movement->id);
        self::assertSame(500, $movement->productId);
        self::assertSame(StockMovementType::PURCHASE_IN, $movement->type);
        self::assertSame(20, $movement->quantityDelta);
        self::assertSame('purchase_order', $movement->referenceType);
        self::assertSame(3, $movement->referenceId);

        [$table, $data] = $connection->inserted[0];
        self::assertSame('test_scp_stock_movements', $table);
        self::assertSame('purchase_in', $data['type']);
        self::assertSame(20, $data['quantity_delta']);
    }

    public function testListBuildsFiltersIntoTheWhereClause(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row(1, 500, 'manual_adjustment', -3, null, null)];
        $repository = new WpdbStockMovementRepository($connection);

        $movements = $repository->list(500, StockMovementType::MANUAL_ADJUSTMENT, '2026-08-01', '2026-08-31');

        self::assertCount(1, $movements);
        self::assertSame(-3, $movements[0]->quantityDelta);
        self::assertNull($movements[0]->referenceType);
    }

    public function testListWithoutFiltersRunsAnUnconditionedQuery(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbStockMovementRepository($connection);

        self::assertSame([], $repository->list());
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        int $id,
        int $productId,
        string $type,
        int $quantityDelta,
        ?string $referenceType,
        ?int $referenceId
    ): array {
        return [
            'id' => (string) $id,
            'product_id' => (string) $productId,
            'type' => $type,
            'quantity_delta' => (string) $quantityDelta,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId !== null ? (string) $referenceId : null,
            'note' => 'not',
            'created_by' => '7',
            'created_at' => '2026-08-01 10:00:00',
        ];
    }
}
