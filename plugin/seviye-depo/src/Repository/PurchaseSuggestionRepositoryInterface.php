<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use Seviye\Depo\Domain\PurchaseSuggestion;
use Seviye\Depo\Domain\PurchaseSuggestionStatus;

interface PurchaseSuggestionRepositoryInterface
{
    public function create(int $productId, int $suggestedQuantity, ?string $reason): PurchaseSuggestion;

    /**
     * True if $productId already has a PENDING suggestion -
     * LowStockPurchaseSuggestionListener uses this to avoid opening a
     * second suggestion every time WC re-fires the low-stock hook for the
     * same still-unresolved product.
     */
    public function hasPending(int $productId): bool;

    public function find(int $id): ?PurchaseSuggestion;

    /**
     * @return list<PurchaseSuggestion>
     */
    public function all(?PurchaseSuggestionStatus $status = null): array;

    /** pending -> dismissed. */
    public function dismiss(int $id): void;

    /** pending -> converted, stamping which purchase order it became. */
    public function convert(int $id, int $purchaseOrderId): void;
}
