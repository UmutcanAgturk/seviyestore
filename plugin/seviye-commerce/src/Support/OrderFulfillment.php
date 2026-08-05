<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

use WC_Order;

/**
 * "Hem şubede hem genel merkezde sipariş hazırlanıyor diyor ama kargoya
 * verildi ve teslim edildi gibi bir özellik yok" - a shipment tracking
 * sub-state layered ON TOP of (never replacing) WooCommerce's own order
 * `status`. Deliberately NOT a new WC order status: `status` already drives
 * hakediş triggering (OrderPersistenceHooks::HAKEDIS_TRIGGER_STATUS =
 * 'completed'), Reports' revenue queries, the weekly digest, and
 * AdminOrdersRestController's refund gate - all keyed on 'completed'
 * specifically. Introducing 'shipped'/'delivered' as WC statuses would mean
 * an order is no longer 'completed' once shipped, breaking every one of
 * those. Storing fulfillment as its own order meta instead means it can be
 * set at any point once an order is accepted (`processing`/`on-hold`/
 * `completed` - see AdminOrdersRestController::FULFILLABLE_STATUSES),
 * independent of exactly when staff also flips the WC status to
 * `completed`.
 *
 * No separate "stage" meta key - the stage is derived from which
 * timestamps are set, so the two can never drift out of sync with each
 * other. "Teslim edildi" does not require "kargoya verildi" first - a
 * school branch may hand an order to a parent in person, with no kargo
 * company/tracking number involved at all.
 *
 * Uses WC_Order's own meta API (`get_meta()`/`update_meta_data()`/`save()`),
 * not `get_post_meta()`/`update_post_meta()` directly - the same
 * HPOS-safe pattern every order-ITEM meta read/write in this plugin
 * already follows (see OrderPersistenceHooks, OrderPresenter), just at the
 * order level instead of the item level.
 */
final class OrderFulfillment
{
    private const SHIPPED_AT_META_KEY = '_scp_shipped_at';
    private const DELIVERED_AT_META_KEY = '_scp_delivered_at';
    private const TRACKING_NUMBER_META_KEY = '_scp_tracking_number';

    public function isDelivered(WC_Order $order): bool
    {
        return $this->metaString($order, self::DELIVERED_AT_META_KEY) !== null;
    }

    public function isShipped(WC_Order $order): bool
    {
        return $this->metaString($order, self::SHIPPED_AT_META_KEY) !== null;
    }

    public function markShipped(WC_Order $order, ?string $trackingNumber): void
    {
        $order->update_meta_data(self::SHIPPED_AT_META_KEY, current_time('mysql'));

        if ($trackingNumber !== null && $trackingNumber !== '') {
            $order->update_meta_data(self::TRACKING_NUMBER_META_KEY, $trackingNumber);
        }

        $order->save();
    }

    public function markDelivered(WC_Order $order): void
    {
        $order->update_meta_data(self::DELIVERED_AT_META_KEY, current_time('mysql'));
        $order->save();
    }

    /**
     * @return array{
     *     fulfillment_status: string,
     *     fulfillment_status_label: ?string,
     *     shipped_at: ?string,
     *     delivered_at: ?string,
     *     tracking_number: ?string
     * }
     */
    public function present(WC_Order $order): array
    {
        $shippedAt = $this->metaString($order, self::SHIPPED_AT_META_KEY);
        $deliveredAt = $this->metaString($order, self::DELIVERED_AT_META_KEY);

        $stage = 'preparing';

        if ($deliveredAt !== null) {
            $stage = 'delivered';
        } elseif ($shippedAt !== null) {
            $stage = 'shipped';
        }

        return [
            'fulfillment_status' => $stage,
            'fulfillment_status_label' => $this->stageLabel($stage),
            'shipped_at' => $shippedAt !== null ? substr($shippedAt, 0, 16) : null,
            'delivered_at' => $deliveredAt !== null ? substr($deliveredAt, 0, 16) : null,
            'tracking_number' => $this->metaString($order, self::TRACKING_NUMBER_META_KEY),
        ];
    }

    private function metaString(WC_Order $order, string $key): ?string
    {
        $value = (string) $order->get_meta($key);

        return $value !== '' ? $value : null;
    }

    /**
     * No label (and no badge in the theme) for 'preparing' - that stage is
     * already covered by the order's own WC status badge ("Hazırlanıyor");
     * a separate fulfillment badge only earns its place once there is
     * something NEW to say.
     */
    private function stageLabel(string $stage): ?string
    {
        return match ($stage) {
            'shipped' => function_exists('__')
                ? __('Kargoya Verildi', 'seviye-commerce')
                : 'Kargoya Verildi',
            'delivered' => function_exists('__')
                ? __('Teslim Edildi', 'seviye-commerce')
                : 'Teslim Edildi',
            default => null,
        };
    }
}
