<?php

declare(strict_types=1);

namespace Seviye\Commerce\Repository;

interface StockSubscriptionRepositoryInterface
{
    /**
     * Idempotent - subscribing twice to the same product leaves a single row.
     */
    public function subscribe(int $productId, int $userId): void;

    public function unsubscribe(int $productId, int $userId): void;

    public function isSubscribed(int $productId, int $userId): bool;

    /**
     * @return list<int> user IDs subscribed to this product, in no
     *     particular order.
     */
    public function subscriberIdsFor(int $productId): array;

    /**
     * Clears every subscription for a product - called once its
     * back-in-stock notification has fired for all subscribers (see
     * {@see \Seviye\Commerce\Http\BackInStockNotificationHooks}), since
     * this is a one-time "notify me next time" subscription, not a
     * persistent watch.
     */
    public function deleteAllFor(int $productId): void;
}
