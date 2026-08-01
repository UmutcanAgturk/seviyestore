<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

/**
 * `productId` points at a WooCommerce product OR variation id
 * (wp_posts.ID) - no FK, this platform never puts FKs on WordPress/
 * WooCommerce core tables (see docs/ARCHITECTURE.md, "Kural"). Variant
 * kalemleri (bkz. Seviye Commerce'in beden/renk varyantları) burada
 * doğrudan kendi varyasyon id'siyle ele alınır, ek bir alan gerekmez.
 */
final class PurchaseOrderItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $purchaseOrderId,
        public readonly int $productId,
        public readonly int $quantityOrdered,
        public readonly int $quantityReceived,
        public readonly ?float $unitCost
    ) {
    }

    public function remainingQuantity(): int
    {
        return max($this->quantityOrdered - $this->quantityReceived, 0);
    }

    public function isFullyReceived(): bool
    {
        return $this->quantityReceived >= $this->quantityOrdered;
    }
}
