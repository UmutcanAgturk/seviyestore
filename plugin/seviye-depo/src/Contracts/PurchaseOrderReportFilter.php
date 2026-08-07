<?php

declare(strict_types=1);

namespace Seviye\Depo\Contracts;

/**
 * All fields optional/nullable - a null field means "don't filter on this",
 * not "match nothing". `fromDate`/`toDate` are inclusive `Y-m-d` dates
 * compared against `created_at` - mirrors Commerce's OrderLineItemFilter.
 *
 * `branchId` is the one exception: it is a three-state sentinel, not a
 * plain nullable filter - `false` (default) means "don't filter, every
 * depo", `null` means "Genel Merkez deposu only", and an `int` means "that
 * branch's own depo only". A plain nullable int cannot express this
 * because Genel Merkez's OWN depo is itself a distinct, selectable scope
 * here (Faz 4) - see PurchaseOrderRepositoryInterface::all()'s identical
 * sentinel inside Seviye\Depo\Repository for the canonical explanation.
 */
final class PurchaseOrderReportFilter
{
    public function __construct(
        public readonly ?int $supplierId = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly int|false|null $branchId = false
    ) {
    }
}
