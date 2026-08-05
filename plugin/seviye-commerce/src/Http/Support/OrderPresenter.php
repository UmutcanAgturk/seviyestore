<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http\Support;

use Seviye\Commerce\Support\OrderFulfillment;
use Seviye\Students\Contracts\StudentLookupInterface;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Serializes a WC_Order (+ its line items) into the JSON shape both
 * OrdersRestController's /commerce/orders/mine (a veli's own order
 * history) and AdminOrdersRestController's /commerce/orders (HQ/Şube
 * Müdürü admin listing) return - extracted here rather than duplicated
 * since both need identical date/money formatting and `_scp_student_id`
 * -> StudentLookupInterface resolution; only the visible-items scope and
 * whether the buyer's own identity is included differ between the two.
 *
 * Also merges in the order's shipment/delivery sub-state (see
 * {@see OrderFulfillment}) - both the veli's own order history AND the
 * admin listing need to show "kargoya verildi/teslim edildi", not just
 * admin, so it lives here rather than being bolted onto only one caller.
 */
final class OrderPresenter
{
    private const STUDENT_META_KEY = '_scp_student_id';

    public function __construct(
        private readonly StudentLookupInterface $students,
        private readonly OrderFulfillment $fulfillment
    ) {
    }

    /**
     * @param array<int, bool>|null $visibleItemIds null shows every item on
     *     the order (the veli's own /mine view - nothing to hide from
     *     them); a non-null set restricts to those WC order-item ids -
     *     AdminOrdersRestController's branch-scoped (Şube Müdürü) view
     *     passes this so an order spanning two branches never leaks
     *     another branch's student/product to a viewer who only owns part
     *     of it.
     * @return array<string, mixed>
     */
    public function present(WC_Order $order, ?array $visibleItemIds = null, bool $includeCustomer = false): array
    {
        $createdAt = $order->get_date_created();

        $items = [];

        foreach ($order->get_items() as $itemId => $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            if ($visibleItemIds !== null && !isset($visibleItemIds[$itemId])) {
                continue;
            }

            $items[] = $this->presentItem($item);
        }

        $presented = [
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'date' => $createdAt !== null ? $createdAt->date('Y-m-d H:i') : null,
            'payment_method_title' => $order->get_payment_method_title(),
            'subtotal' => (float) $order->get_subtotal(),
            'total_tax' => (float) $order->get_total_tax(),
            'total' => (float) $order->get_total(),
            'refunded_total' => (float) $order->get_total_refunded(),
            'items' => array_values($items),
        ];

        $presented = array_merge($presented, $this->fulfillment->present($order));

        if ($includeCustomer) {
            $name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            $presented['customer_name'] = $name !== '' ? $name : __('Bilinmiyor', 'seviye-commerce');
            $presented['customer_email'] = $order->get_billing_email();
        }

        return $presented;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(WC_Order_Item_Product $item): array
    {
        $studentId = (int) $item->get_meta(self::STUDENT_META_KEY);
        $student = $studentId > 0 ? $this->students->find($studentId) : null;
        $quantity = max(1, $item->get_quantity());

        return [
            'product_id' => $item->get_product_id(),
            'name' => $item->get_name(),
            'quantity' => $quantity,
            'unit_price' => (float) $item->get_total() / $quantity,
            'line_total' => (float) $item->get_total(),
            'line_tax' => (float) $item->get_total_tax(),
            'student_name' => $student !== null ? trim($student->firstName . ' ' . $student->lastName) : null,
        ];
    }
}
