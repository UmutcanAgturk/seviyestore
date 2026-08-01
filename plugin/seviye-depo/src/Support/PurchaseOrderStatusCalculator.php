<?php

declare(strict_types=1);

namespace Seviye\Depo\Support;

use Seviye\Depo\Domain\PurchaseOrderItem;
use Seviye\Depo\Domain\PurchaseOrderStatus;

/**
 * Pure decision logic behind {@see \Seviye\Depo\Repository\WpdbPurchaseOrderRepository::receiveItem()} -
 * kept separate from the repository (same reasoning as Commerce's
 * CartPricingService/SplitPaymentCalculator) so the actual "ne zaman
 * tamamlandı sayılır" business rule is unit-testable without a database
 * fake.
 */
final class PurchaseOrderStatusCalculator
{
    /**
     * @param list<PurchaseOrderItem> $items
     */
    public function recalculate(PurchaseOrderStatus $current, array $items): PurchaseOrderStatus
    {
        if (!in_array($current, [PurchaseOrderStatus::SENT, PurchaseOrderStatus::PARTIALLY_RECEIVED], true)) {
            return $current;
        }

        if ($items === []) {
            return $current;
        }

        $allFullyReceived = true;
        $anyReceived = false;

        foreach ($items as $item) {
            $allFullyReceived = $allFullyReceived && $item->isFullyReceived();
            $anyReceived = $anyReceived || $item->quantityReceived > 0;
        }

        return match (true) {
            $allFullyReceived => PurchaseOrderStatus::COMPLETED,
            $anyReceived => PurchaseOrderStatus::PARTIALLY_RECEIVED,
            default => $current,
        };
    }
}
