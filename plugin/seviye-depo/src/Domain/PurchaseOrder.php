<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

final class PurchaseOrder
{
    /**
     * @param list<PurchaseOrderItem> $items
     */
    public function __construct(
        public readonly int $id,
        public readonly int $supplierId,
        public readonly string $code,
        public readonly PurchaseOrderStatus $status,
        public readonly ?string $expectedDate,
        public readonly ?string $note,
        public readonly int $createdByUserId,
        public readonly string $createdAt,
        public readonly array $items,
        public readonly ?string $supplierShippedAt = null
    ) {
    }
}
