<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Core\Events\Event;
use Seviye\Core\Events\EventBusInterface;
use WC_Product;

/**
 * "Bir ürünün stoğu belirlenen eşiğin altına düşünce Genel Merkez/Şube'ye
 * otomatik bildirim." Hooks WooCommerce's own extensibility points built
 * for exactly this -
 * `woocommerce_product_low_stock_notification` (simple/variable parent) and
 * `woocommerce_variation_low_stock_notification` (a single variation) -
 * fired by WC core itself once a product's stock crosses its low-stock
 * threshold (the per-product `_low_stock_amount` set via
 * ProductsRestController, or the site-wide default) after an order reduces
 * it. This does not reimplement threshold-crossing detection - WC core
 * already does that reliably - it only turns the moment into a
 * platform-native EventBus event so Seviye Notifications can route it
 * through email/panel like every other notification on this platform.
 * WooCommerce's own built-in low-stock admin email (if configured) keeps
 * running independently; this is a separate, additional channel.
 */
final class LowStockNotificationHooks
{
    public function __construct(private readonly EventBusInterface $eventBus)
    {
    }

    public function register(): void
    {
        add_action('woocommerce_product_low_stock_notification', [$this, 'onLowStock']);
        add_action('woocommerce_variation_low_stock_notification', [$this, 'onLowStock']);
    }

    public function onLowStock(WC_Product $product): void
    {
        $parentId = $product->get_parent_id();

        $this->eventBus->dispatch(new Event('commerce.product_low_stock', [
            'product_id' => $parentId > 0 ? $parentId : $product->get_id(),
            'variation_id' => $parentId > 0 ? $product->get_id() : null,
            'product_name' => $product->get_name(),
            'stock_quantity' => $product->get_stock_quantity(),
        ]));
    }
}
