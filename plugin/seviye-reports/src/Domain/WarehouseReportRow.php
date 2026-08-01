<?php

declare(strict_types=1);

namespace Seviye\Reports\Domain;

/**
 * One supplier group in "Depo Raporları" - the unit CsvExporter and
 * XlsxExporter both render (exportWarehouse()/warehouseSheetXml()), one row
 * per instance. Mirrors SalesReportRow's role for the sales report.
 *
 * `onTimeRate` is null when the supplier has no COMPLETED order that also
 * had an expected_date set - there is nothing to compute a rate FROM, not
 * "0% on time". See Support\WarehouseReportBuilder::build().
 */
final class WarehouseReportRow
{
    public function __construct(
        public readonly int $supplierId,
        public readonly string $supplierName,
        public readonly int $orderCount,
        public readonly float $totalCost,
        public readonly int $completedOrderCount,
        public readonly ?float $onTimeRate
    ) {
    }
}
