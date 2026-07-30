<?php

declare(strict_types=1);

namespace Seviye\Reports\Domain;

/**
 * One branch+product group in a sales report - the unit CsvExporter and
 * XlsxExporter both render, one row per instance.
 */
final class SalesReportRow
{
    public function __construct(
        public readonly int $branchId,
        public readonly string $branchName,
        public readonly int $productId,
        public readonly string $productName,
        public readonly int $orderCount,
        public readonly float $totalPrice,
        public readonly float $totalVat
    ) {
    }
}
