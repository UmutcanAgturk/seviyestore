<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Repository\StockSubscriptionRepositoryInterface;
use Seviye\Core\Events\Event;
use Seviye\Core\Events\EventBusInterface;
use WC_Product;

/**
 * "Stok gelince haber ver" - a veli subscribes on an out-of-stock product's
 * page (see StockSubscriptionsRestController); once the product transitions
 * BACK to in-stock, every subscriber gets notified. Detecting the
 * transition (not just "is currently in stock") uses WooCommerce's own
 * `woocommerce_product_object_updated_props` hook, fired after
 * `WC_Product::save()` with the list of props that actually CHANGED -
 * checking `in_array('stock_status', $updatedProps, true)` means this only
 * fires on a real transition, never on every save of an already-in-stock
 * product (unlike naively checking `$product->get_stock_status()` alone on
 * every save, which would re-fire on unrelated edits).
 *
 * Unlike LowStockNotificationHooks (one platform-wide EventBus event, HQ
 * reads it), this dispatches ONE `commerce.stock_subscription_fulfilled`
 * event PER SUBSCRIBER - each subscriber is a distinct recipient with their
 * own notification, mirroring OrderPersistenceHooks'/order-placed's
 * single-customer-per-event shape rather than the HQ-broadcast shape.
 * Subscriptions are cleared right after (one-time "notify me next time",
 * not a persistent watch - see CreateStockSubscriptionsTable's docblock).
 */
final class BackInStockNotificationHooks
{
    public function __construct(
        private readonly StockSubscriptionRepositoryInterface $repository,
        private readonly EventBusInterface $eventBus
    ) {
    }

    public function register(): void
    {
        add_action('woocommerce_product_object_updated_props', [$this, 'onProductUpdated'], 10, 2);
    }

    /**
     * @param array<int, string> $updatedProps
     */
    public function onProductUpdated(WC_Product $product, array $updatedProps): void
    {
        if (!in_array('stock_status', $updatedProps, true)) {
            return;
        }

        if ($product->get_stock_status() !== 'instock') {
            return;
        }

        $parentId = $product->get_parent_id();
        $productId = $parentId > 0 ? $parentId : $product->get_id();

        $subscriberIds = $this->repository->subscriberIdsFor($productId);

        if ($subscriberIds === []) {
            return;
        }

        foreach ($subscriberIds as $userId) {
            $this->eventBus->dispatch(new Event('commerce.stock_subscription_fulfilled', [
                'user_id' => $userId,
                'product_id' => $productId,
                'product_name' => $product->get_name(),
            ]));
        }

        $this->repository->deleteAllFor($productId);
    }
}
