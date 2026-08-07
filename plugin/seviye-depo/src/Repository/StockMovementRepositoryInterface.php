<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use Seviye\Depo\Domain\StockMovement;
use Seviye\Depo\Domain\StockMovementType;

/**
 * Append-only - there is deliberately no update()/delete() here, see
 * {@see \Seviye\Depo\Domain\StockMovement}'s docblock.
 */
interface StockMovementRepositoryInterface
{
    public function record(
        int $productId,
        StockMovementType $type,
        int $quantityDelta,
        ?string $referenceType,
        ?int $referenceId,
        ?string $note,
        int $createdByUserId,
        ?int $branchId = null
    ): StockMovement;

    /**
     * $branchId === false: filtre yok. null: yalnızca Genel Merkez deposu.
     * int: yalnızca o şubenin deposu - bkz. PurchaseOrderRepositoryInterface::all().
     *
     * @return list<StockMovement>
     */
    public function list(
        ?int $productId = null,
        ?StockMovementType $type = null,
        ?string $from = null,
        ?string $to = null,
        int|false|null $branchId = false
    ): array;
}
