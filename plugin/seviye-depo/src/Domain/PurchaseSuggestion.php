<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

/**
 * `productId` bir WC ürün/varyasyon id'si (wp_posts.ID), FK yok - aynı
 * platform kuralı. `convertedPurchaseOrderId`, yalnızca status=CONVERTED
 * olduğunda dolu - hangi satın alma siparişine dönüştürüldüğünü tutar
 * (bkz. WpdbPurchaseSuggestionRepository::convert()).
 */
final class PurchaseSuggestion
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly int $suggestedQuantity,
        public readonly PurchaseSuggestionStatus $status,
        public readonly ?string $reason,
        public readonly ?int $convertedPurchaseOrderId,
        public readonly string $createdAt
    ) {
    }
}
