<?php

declare(strict_types=1);

namespace Seviye\Reports\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Commerce\Contracts\OrderLineItemRecord;
use Seviye\Reports\Support\SalesReportBuilder;

final class SalesReportBuilderTest extends TestCase
{
    public function testGroupsByBranchAndProduct(): void
    {
        $builder = new SalesReportBuilder();

        $records = [
            $this->record(branchId: 7, productId: 55, price: 100.0, vat: 18.0),
            $this->record(branchId: 7, productId: 55, price: 100.0, vat: 18.0),
            $this->record(branchId: 7, productId: 56, price: 50.0, vat: 9.0),
            $this->record(branchId: 8, productId: 55, price: 200.0, vat: 36.0),
        ];

        $rows = $builder->build($records, [7 => 'Kadıköy', 8 => 'Ankara'], [55 => 'Matematik Kitabı', 56 => 'Defter']);

        self::assertCount(3, $rows);

        $kadikoyMath = $this->findRow($rows, 7, 55);
        self::assertSame('Kadıköy', $kadikoyMath->branchName);
        self::assertSame('Matematik Kitabı', $kadikoyMath->productName);
        self::assertSame(2, $kadikoyMath->orderCount);
        self::assertSame(200.0, $kadikoyMath->totalPrice);
        self::assertSame(36.0, $kadikoyMath->totalVat);

        $kadikoyNotebook = $this->findRow($rows, 7, 56);
        self::assertSame(1, $kadikoyNotebook->orderCount);
        self::assertSame(50.0, $kadikoyNotebook->totalPrice);

        $ankaraMath = $this->findRow($rows, 8, 55);
        self::assertSame('Ankara', $ankaraMath->branchName);
        self::assertSame(1, $ankaraMath->orderCount);
        self::assertSame(200.0, $ankaraMath->totalPrice);
    }

    public function testEmptyInputProducesNoRows(): void
    {
        self::assertSame([], (new SalesReportBuilder())->build([], [], []));
    }

    public function testMissingNameLookupFallsBackToAnEmptyString(): void
    {
        $builder = new SalesReportBuilder();

        $rows = $builder->build([$this->record(branchId: 7, productId: 55, price: 10.0, vat: 1.0)], [], []);

        self::assertSame('', $rows[0]->branchName);
        self::assertSame('', $rows[0]->productName);
    }

    public function testTotalsRoundToTwoDecimals(): void
    {
        $builder = new SalesReportBuilder();

        $records = [
            $this->record(branchId: 7, productId: 55, price: 10.111, vat: 1.111),
            $this->record(branchId: 7, productId: 55, price: 10.111, vat: 1.111),
        ];

        $rows = $builder->build($records, [], []);

        self::assertSame(20.22, $rows[0]->totalPrice);
        self::assertSame(2.22, $rows[0]->totalVat);
    }

    private function record(int $branchId, int $productId, float $price, float $vat): OrderLineItemRecord
    {
        return new OrderLineItemRecord(1, 500, 3, 42, $branchId, $productId, 12.5, $price, $vat, 'completed', '2026-07-30 10:00:00');
    }

    /**
     * @param list<\Seviye\Reports\Domain\SalesReportRow> $rows
     */
    private function findRow(array $rows, int $branchId, int $productId): \Seviye\Reports\Domain\SalesReportRow
    {
        foreach ($rows as $row) {
            if ($row->branchId === $branchId && $row->productId === $productId) {
                return $row;
            }
        }

        self::fail("No row found for branch {$branchId} / product {$productId}");
    }
}
