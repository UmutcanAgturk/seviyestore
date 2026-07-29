<?php

declare(strict_types=1);

namespace Seviye\Finance\Domain;

/**
 * One immutable, append-only ledger entry: a branch's earned or reversed
 * share of a single order line item, recorded from
 * Seviye\Commerce\Http\OrderPersistenceHooks' `commerce.order_line_item_completed`
 * / `commerce.order_line_item_reversed` events. Never edited after
 * creation - a mistake is corrected by posting a new, opposite-signed
 * entry, the same immutability principle as Parents' KVKK consent
 * timestamp, applied to money instead of consent.
 *
 * `amount` is signed: positive for EARNED, negative for REVERSED, so a
 * branch's balance is always a plain SUM() over its entries.
 */
final class HakedisEntry
{
    public function __construct(
        public readonly int $id,
        public readonly int $branchId,
        public readonly int $orderId,
        public readonly int $orderItemId,
        public readonly int $studentId,
        public readonly float $amount,
        public readonly float $commissionRate,
        public readonly float $price,
        public readonly HakedisEntryType $type
    ) {
    }
}
