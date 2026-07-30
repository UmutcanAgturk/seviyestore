<?php

declare(strict_types=1);

namespace Seviye\Commerce\Contracts;

/**
 * All fields optional/nullable - a null field means "don't filter on this",
 * not "match nothing". `fromDate`/`toDate` are inclusive `Y-m-d` dates
 * compared against `created_at`.
 */
final class OrderLineItemFilter
{
    public function __construct(
        public readonly ?int $branchId = null,
        public readonly ?int $productId = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $status = null
    ) {
    }
}
