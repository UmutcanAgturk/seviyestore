<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Commerce\Domain\OrderLineItem;
use Seviye\Commerce\Repository\OrderLineItemRepositoryInterface;
use Seviye\Commerce\Support\SplitPaymentCalculator;
use Seviye\Core\Events\Event;
use Seviye\Core\Events\EventBusInterface;
use Seviye\Students\Contracts\StudentLookupInterface;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Thin WooCommerce hook adapter - not unit tested, same as
 * {@see WooCommerceCartHooks} (see docs/ARCHITECTURE.md, "Test stratejisi").
 * Persists one scp_order_line_items row per order line item that carries a
 * `_scp_student_id` meta value (attached by WooCommerceCartHooks::persistStudentId()
 * during checkout), keeps their `status` in sync with the WooCommerce
 * order's own status as it changes, and fires one "hakediş" event per line
 * item when the order reaches HAKEDIS_TRIGGER_STATUS (or a reversal event
 * if a previously-completed order is later refunded/cancelled) - the actual
 * ledger/hakediş bookkeeping is Seviye Finance's job (not built yet),
 * Commerce's responsibility ends at firing the event with everything
 * Finance will need.
 *
 * Also fires ONE `commerce.order_placed` event per order (not per item,
 * unlike the hakediş events) right after checkout - "veliler sipariş
 * verdiğinde otomatik olarak velilerin mailine mail gidecek bir sistem".
 * The payload carries everything a notification needs to compose an email
 * (order number/total/items) so Seviye Notifications' listener never has to
 * touch WC_Order itself, the same "payload is self-contained" rule
 * hakedisPayload() already follows for Finance.
 */
final class OrderPersistenceHooks
{
    private const STUDENT_META_KEY = '_scp_student_id';

    /**
     * "completed" rather than "processing": a processing order can still be
     * refunded/cancelled, and hakediş should not fire on money that might
     * yet be returned.
     */
    private const HAKEDIS_TRIGGER_STATUS = 'completed';

    /**
     * A previously-completed order moving to one of these statuses reverses
     * the hakediş already fired for it - without this, a refund after
     * completion would leave a branch credited for commission on money that
     * was given back.
     */
    private const HAKEDIS_REVERSAL_STATUSES = ['refunded', 'cancelled'];

    public function __construct(
        private readonly OrderLineItemRepositoryInterface $orderLineItems,
        private readonly StudentLookupInterface $students,
        private readonly BranchLookupInterface $branches,
        private readonly SplitPaymentCalculator $splitPaymentCalculator,
        private readonly EventBusInterface $eventBus
    ) {
    }

    public function register(): void
    {
        add_action('woocommerce_checkout_order_processed', [$this, 'persistOrderLineItems'], 10, 3);
        add_action('woocommerce_order_status_changed', [$this, 'syncOrderStatus'], 10, 3);
    }

    /**
     * @param array<string, mixed> $postedData
     */
    public function persistOrderLineItems(int $orderId, array $postedData, WC_Order $order): void
    {
        $persistedAny = false;

        foreach ($order->get_items() as $orderItemId => $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $studentId = (int) $item->get_meta(self::STUDENT_META_KEY, true);

            if ($studentId <= 0) {
                continue;
            }

            $studentSummary = $this->students->find($studentId);

            if ($studentSummary === null) {
                continue;
            }

            $branchSummary = $this->branches->find($studentSummary->branchId);

            if ($branchSummary === null) {
                continue;
            }

            $this->orderLineItems->create(
                $orderId,
                (int) $orderItemId,
                $studentId,
                $branchSummary->id,
                $item->get_product_id(),
                $branchSummary->commissionRate,
                (float) $item->get_total(),
                (float) $item->get_total_tax(),
                $order->get_status()
            );

            $persistedAny = true;
        }

        if ($persistedAny) {
            $this->eventBus->dispatch(new Event('commerce.order_placed', $this->orderPlacedPayload($order)));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPlacedPayload(WC_Order $order): array
    {
        return [
            'order_id' => $order->get_id(),
            'customer_id' => $order->get_customer_id(),
            'order_number' => $order->get_order_number(),
            'total' => (float) $order->get_total(),
            'items' => array_values(array_map(
                static fn (WC_Order_Item_Product $item): array => [
                    'name' => $item->get_name(),
                    'quantity' => max(1, $item->get_quantity()),
                    'line_total' => (float) $item->get_total(),
                ],
                array_filter(
                    $order->get_items(),
                    static fn ($item): bool => $item instanceof WC_Order_Item_Product
                )
            )),
        ];
    }

    public function syncOrderStatus(int $orderId, string $oldStatus, string $newStatus): void
    {
        $this->orderLineItems->updateStatusForOrder($orderId, $newStatus);

        if ($newStatus === self::HAKEDIS_TRIGGER_STATUS) {
            $this->fireHakedisEvents($orderId, 'commerce.order_line_item_completed');

            return;
        }

        $isReversal = $oldStatus === self::HAKEDIS_TRIGGER_STATUS
            && in_array($newStatus, self::HAKEDIS_REVERSAL_STATUSES, true);

        if ($isReversal) {
            $this->fireHakedisEvents($orderId, 'commerce.order_line_item_reversed');
        }
    }

    private function fireHakedisEvents(int $orderId, string $eventName): void
    {
        foreach ($this->orderLineItems->findByOrder($orderId) as $item) {
            $this->eventBus->dispatch(new Event($eventName, $this->hakedisPayload($item)));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function hakedisPayload(OrderLineItem $item): array
    {
        $split = $this->splitPaymentCalculator->calculate($item->price, $item->commissionRate);

        return [
            'order_id' => $item->orderId,
            'order_item_id' => $item->orderItemId,
            'student_id' => $item->studentId,
            'branch_id' => $item->branchId,
            'commission_rate' => $item->commissionRate,
            'price' => $item->price,
            'vat_amount' => $item->vatAmount,
            'branch_share' => $split->branchShare,
            'hq_share' => $split->hqShare,
        ];
    }
}
