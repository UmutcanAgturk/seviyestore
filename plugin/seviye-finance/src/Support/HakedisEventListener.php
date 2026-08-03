<?php

declare(strict_types=1);

namespace Seviye\Finance\Support;

use Seviye\Core\Events\Event;
use Seviye\Finance\Domain\HakedisEntryType;
use Seviye\Finance\Repository\HakedisRepositoryInterface;

/**
 * Fully pure and unit-testable, unlike Commerce's Http adapters: it only
 * touches Core's own Event object and this module's own repository, never
 * a WordPress/WooCommerce function directly - EventBus itself is the
 * WP-independent layer (see docs/ARCHITECTURE.md, bölüm 2).
 *
 * Listens for `commerce.order_line_item_completed` /
 * `commerce.order_line_item_reversed` / `commerce.order_line_item_partially_reversed`,
 * dispatched by Seviye\Commerce\Http\OrderPersistenceHooks - this module never depends on
 * Seviye\Commerce's classes or Contracts, only on that documented event
 * name/payload shape. See docs/ARCHITECTURE.md, bölüm 15 for why this is a
 * deliberately looser coupling than the Contracts pattern used elsewhere.
 */
final class HakedisEventListener
{
    public function __construct(private readonly HakedisRepositoryInterface $hakedis)
    {
    }

    public function onOrderLineItemCompleted(Event $event): void
    {
        $this->recordIfNotAlreadyRecorded($event, HakedisEntryType::EARNED, $this->branchShare($event));
    }

    public function onOrderLineItemReversed(Event $event): void
    {
        $this->recordIfNotAlreadyRecorded($event, HakedisEntryType::REVERSED, -$this->branchShare($event));
    }

    /**
     * "Kısmi iade -> hakediş orantılı ters kayıt": reverses only the
     * FRACTION of this item's branch_share that the refund covers
     * (`reversal_ratio`, computed by Commerce as refund amount / order
     * total - see OrderPersistenceHooks::onOrderRefunded()), not the whole
     * amount. Idempotency is keyed on refund_id rather than just
     * order_item_id, since - unlike EARNED/REVERSED - the SAME item can be
     * partially reversed more than once across separate refunds.
     */
    public function onOrderLineItemPartiallyReversed(Event $event): void
    {
        $ratio = (float) $event->get('reversal_ratio', 0.0);
        $refundId = (int) $event->get('refund_id');

        $this->recordIfNotAlreadyRecorded(
            $event,
            HakedisEntryType::PARTIAL_REVERSAL,
            -($this->branchShare($event) * $ratio),
            $refundId
        );
    }

    private function branchShare(Event $event): float
    {
        return (float) $event->get('branch_share', 0.0);
    }

    private function recordIfNotAlreadyRecorded(
        Event $event,
        HakedisEntryType $type,
        float $signedAmount,
        ?int $refundId = null
    ): void {
        $orderId = (int) $event->get('order_id');
        $orderItemId = (int) $event->get('order_item_id');

        if ($this->hakedis->entryExists($orderId, $orderItemId, $type, $refundId)) {
            return;
        }

        $this->hakedis->record(
            (int) $event->get('branch_id'),
            $orderId,
            $orderItemId,
            (int) $event->get('student_id'),
            $signedAmount,
            (float) $event->get('commission_rate'),
            (float) $event->get('price'),
            (float) $event->get('vat_amount', 0.0),
            $type,
            $refundId
        );
    }
}
