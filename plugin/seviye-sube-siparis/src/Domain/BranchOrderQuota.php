<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Domain;

/**
 * "Her bir şube için ayrı ayrı ürün girişi yapılabilsin ve sayıları Genel
 * Merkez her ürün için ayrı ayrı belirlesin" - bir (branchId, productId)
 * çiftinin ücretsiz hakkı. Satır yoksa o şube o ürün için hiç ücretsiz
 * kotaya sahip değildir (freeQuantity=0 ile aynı anlama gelir, ayrı bir
 * "kota yok" durumu yok - bkz. Repository\BranchOrderQuotaRepositoryInterface::find()).
 */
final class BranchOrderQuota
{
    public function __construct(
        public readonly int $id,
        public readonly int $branchId,
        public readonly int $productId,
        public readonly int $freeQuantity,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {
    }
}
