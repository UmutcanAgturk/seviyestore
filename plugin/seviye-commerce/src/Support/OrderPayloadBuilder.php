<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

use WC_Order;
use WC_Order_Item_Product;

/**
 * Shared payload shape for every order-lifecycle notification event
 * (`commerce.order_placed`/`commerce.order_cancelled`/`commerce.order_refunded`/
 * `commerce.order_shipped`/`commerce.order_delivered`) - self-contained
 * (order number/total/items), the same rule hakedisPayload() follows for
 * Finance, so Seviye Notifications' listeners never have to touch WC_Order
 * themselves. Extracted from OrderPersistenceHooks' own former private
 * orderPayload() method so AdminOrdersRestController's new ship()/deliver()
 * actions (bölüm "kargoya verildi/teslim edildi") can build the identical
 * shape without duplicating it.
 */
final class OrderPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(WC_Order $order): array
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
}
