<?php

declare(strict_types=1);

namespace Seviye\Reports\Domain;

/**
 * One (supplier, depo) group in "Depo Raporları" - the unit CsvExporter and
 * XlsxExporter both render (exportWarehouse()/warehouseSheetXml()), one row
 * per instance. Mirrors SalesReportRow's role for the sales report.
 *
 * `onTimeRate` is null when the group has no COMPLETED order that also had
 * an expected_date set - there is nothing to compute a rate FROM, not "0%
 * on time". See Support\WarehouseReportBuilder::build().
 *
 * Faz 4: `branchId`/`branchName` split each supplier into a Genel Merkez
 * row (branchId null) and one row per branch that has ordered from that
 * supplier - a supplier is shared/platform-wide (see SuppliersRestController's
 * own docblock), but its ORDERS are not, so mixing HQ's and a branch's
 * purchase history into one supplier total would misrepresent both.
 */
final class WarehouseReportRow
{
    public function __construct(
        public readonly int $supplierId,
        public readonly string $supplierName,
        public readonly int $orderCount,
        public readonly float $totalCost,
        public readonly int $completedOrderCount,
        public readonly ?float $onTimeRate,
        public readonly ?int $branchId = null,
        public readonly string $branchName = ''
    ) {
    }
}
