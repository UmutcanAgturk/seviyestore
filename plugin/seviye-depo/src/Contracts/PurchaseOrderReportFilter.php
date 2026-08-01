<?php

declare(strict_types=1);

namespace Seviye\Depo\Contracts;

/**
 * All fields optional/nullable - a null field means "don't filter on this",
 * not "match nothing". `fromDate`/`toDate` are inclusive `Y-m-d` dates
 * compared against `created_at` - mirrors Commerce's OrderLineItemFilter.
 */
final class PurchaseOrderReportFilter
{
    public function __construct(
        public readonly ?int $supplierId = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null
    ) {
    }
}
