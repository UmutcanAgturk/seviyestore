<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

final class StockCount
{
    /**
     * @param list<StockCountItem> $items
     */
    public function __construct(
        public readonly int $id,
        public readonly StockCountStatus $status,
        public readonly int $startedByUserId,
        public readonly ?int $completedByUserId,
        public readonly string $startedAt,
        public readonly ?string $completedAt,
        public readonly array $items,
        public readonly ?int $branchId = null
    ) {
    }
}
