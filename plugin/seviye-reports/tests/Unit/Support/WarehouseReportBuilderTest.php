<?php

declare(strict_types=1);

namespace Seviye\Reports\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Depo\Contracts\PurchaseOrderReportRecord;
use Seviye\Reports\Support\WarehouseReportBuilder;

final class WarehouseReportBuilderTest extends TestCase
{
    public function testGroupsBySupplierAndSumsCost(): void
    {
        $builder = new WarehouseReportBuilder();

        $records = [
            $this->record(supplierId: 3, status: 'draft', totalCost: 100.0),
            $this->record(supplierId: 3, status: 'sent', totalCost: 200.0),
            $this->record(supplierId: 5, status: 'draft', totalCost: 50.0),
        ];

        $rows = $builder->build($records, [3 => 'Okul Tekstil', 5 => 'Kırtasiye A.Ş.']);

        self::assertCount(2, $rows);

        $supplier3 = $this->findRow($rows, 3);
        self::assertSame('Okul Tekstil', $supplier3->supplierName);
        self::assertSame(2, $supplier3->orderCount);
        self::assertSame(300.0, $supplier3->totalCost);

        $supplier5 = $this->findRow($rows, 5);
        self::assertSame(1, $supplier5->orderCount);
        self::assertSame(50.0, $supplier5->totalCost);
    }

    public function testEmptyInputProducesNoRows(): void
    {
        self::assertSame([], (new WarehouseReportBuilder())->build([], []));
    }

    public function testMissingNameLookupFallsBackToAnEmptyString(): void
    {
        $builder = new WarehouseReportBuilder();

        $rows = $builder->build([$this->record(supplierId: 3, status: 'draft', totalCost: 10.0)], []);

        self::assertSame('', $rows[0]->supplierName);
    }

    public function testOnTimeRateIsNullWhenSupplierHasNoTimedCompletedOrder(): void
    {
        $builder = new WarehouseReportBuilder();

        $rows = $builder->build([
            $this->record(supplierId: 3, status: 'sent', totalCost: 100.0),
            $this->record(supplierId: 3, status: 'completed', totalCost: 100.0, expectedDate: null),
        ], []);

        self::assertSame(1, $rows[0]->completedOrderCount);
        self::assertNull($rows[0]->onTimeRate);
    }

    public function testOnTimeRateCountsCompletedAtOnOrBeforeExpectedDateAsOnTime(): void
    {
        $builder = new WarehouseReportBuilder();

        $records = [
            $this->record(supplierId: 3, status: 'completed', totalCost: 100.0, expectedDate: '2026-07-10', completedAt: '2026-07-10 12:00:00'),
            $this->record(supplierId: 3, status: 'completed', totalCost: 100.0, expectedDate: '2026-07-10', completedAt: '2026-07-09 12:00:00'),
            $this->record(supplierId: 3, status: 'completed', totalCost: 100.0, expectedDate: '2026-07-10', completedAt: '2026-07-12 12:00:00'),
        ];

        $rows = $builder->build($records, []);

        self::assertSame(3, $rows[0]->completedOrderCount);
        self::assertSame(66.7, $rows[0]->onTimeRate);
    }

    public function testUncompletedAndUntimedOrdersDoNotAffectOnTimeRate(): void
    {
        $builder = new WarehouseReportBuilder();

        $records = [
            $this->record(supplierId: 3, status: 'completed', totalCost: 100.0, expectedDate: '2026-07-10', completedAt: '2026-07-10 12:00:00'),
            $this->record(supplierId: 3, status: 'draft', totalCost: 100.0),
            $this->record(supplierId: 3, status: 'completed', totalCost: 100.0, expectedDate: null),
        ];

        $rows = $builder->build($records, []);

        self::assertSame(100.0, $rows[0]->onTimeRate);
    }

    private function record(
        int $supplierId,
        string $status,
        float $totalCost,
        ?string $expectedDate = '2026-07-10',
        ?string $completedAt = null
    ): PurchaseOrderReportRecord {
        return new PurchaseOrderReportRecord(
            1,
            $supplierId,
            $status,
            $expectedDate,
            $status === 'completed' ? ($completedAt ?? '2026-07-10 12:00:00') : null,
            $totalCost,
            '2026-07-01 08:00:00'
        );
    }

    /**
     * @param list<\Seviye\Reports\Domain\WarehouseReportRow> $rows
     */
    private function findRow(array $rows, int $supplierId): \Seviye\Reports\Domain\WarehouseReportRow
    {
        foreach ($rows as $row) {
            if ($row->supplierId === $supplierId) {
                return $row;
            }
        }

        self::fail("No row found for supplier {$supplierId}");
    }
}
