<?php

declare(strict_types=1);

namespace Seviye\Commerce\Repository;

use Seviye\Commerce\Domain\OrderLineItem;

interface OrderLineItemRepositoryInterface
{
    public function create(
        int $orderId,
        int $orderItemId,
        int $studentId,
        int $branchId,
        float $commissionRate,
        float $price,
        string $status
    ): OrderLineItem;

    public function updateStatusForOrder(int $orderId, string $status): void;

    /**
     * @return list<OrderLineItem>
     */
    public function findByOrder(int $orderId): array;
}
