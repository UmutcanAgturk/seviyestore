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
 *
 * `vatAmount` is carried through unsigned (not flipped on REVERSED like
 * `amount` is) - it is a record of how much VAT WooCommerce charged on the
 * original order line item, not a share owed to the branch, so there is no
 * "negative VAT" concept to represent here. It exists purely as captured
 * accounting data for Seviye Reports (not yet built) to consume later; this
 * module makes no use of it itself.
 *
 * `refundId` is null for EARNED/REVERSED (stored as the DB sentinel 0 -
 * see the refund_id column's docblock in CreateHakedisEntriesTable), and
 * the WooCommerce refund post ID for PARTIAL_REVERSAL.
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
        public readonly float $vatAmount,
        public readonly HakedisEntryType $type,
        public readonly ?int $refundId = null
    ) {
    }
}
