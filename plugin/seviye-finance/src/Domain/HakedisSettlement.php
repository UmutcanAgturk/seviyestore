<?php

declare(strict_types=1);

namespace Seviye\Finance\Domain;

/**
 * One immutable, append-only record of HQ actually paying out part of a
 * branch's accrued hakediş balance - never edited after creation, same
 * immutability principle as HakedisEntry, and for the same reason: money
 * history is corrected by posting a new record, not by rewriting an old
 * one. `amount` is always positive (a settlement only ever reduces what a
 * branch is still owed; there is no "reversed settlement" concept here -
 * HQ correcting an overpayment records a smaller amount next time, it does
 * not edit or negate a past payout).
 *
 * `createdAt` is a deliberate, documented exception to this platform's
 * usual rule that domain entities never carry a raw timestamp (see
 * HakedisEntry, OrderLineItem, and every other Domain class): the whole
 * point of "tahsilat işaretleme" is answering *when* a branch was paid, so
 * unlike every prior entity, an actual consumer (the settlement history
 * list) genuinely needs this field.
 */
final class HakedisSettlement
{
    public function __construct(
        public readonly int $id,
        public readonly int $branchId,
        public readonly float $amount,
        public readonly SettlementMethod $method,
        public readonly ?string $note,
        public readonly int $recordedByUserId,
        public readonly string $createdAt
    ) {
    }
}
