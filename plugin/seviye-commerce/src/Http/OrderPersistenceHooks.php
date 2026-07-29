<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Commerce\Repository\OrderLineItemRepositoryInterface;
use Seviye\Students\Contracts\StudentLookupInterface;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Thin WooCommerce hook adapter - not unit tested, same as
 * {@see WooCommerceCartHooks} (see docs/ARCHITECTURE.md, "Test stratejisi").
 * Persists one scp_order_line_items row per order line item that carries a
 * `_scp_student_id` meta value (attached by WooCommerceCartHooks::persistStudentId()
 * during checkout), and keeps their `status` in sync with the WooCommerce
 * order's own status as it changes.
 */
final class OrderPersistenceHooks
{
    private const STUDENT_META_KEY = '_scp_student_id';

    public function __construct(
        private readonly OrderLineItemRepositoryInterface $orderLineItems,
        private readonly StudentLookupInterface $students,
        private readonly BranchLookupInterface $branches
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
                $branchSummary->commissionRate,
                (float) $item->get_total(),
                $order->get_status()
            );
        }
    }

    public function syncOrderStatus(int $orderId, string $oldStatus, string $newStatus): void
    {
        $this->orderLineItems->updateStatusForOrder($orderId, $newStatus);
    }
}
