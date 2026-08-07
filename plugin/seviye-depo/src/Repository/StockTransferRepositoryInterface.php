<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use Seviye\Depo\Domain\StockTransfer;
use Seviye\Depo\Domain\StockTransferStatus;

interface StockTransferRepositoryInterface
{
    /**
     * $fromBranchId/$toBranchId are frozen at write time by the caller
     * (StockTransfersRestController::store()), resolved from each
     * product's own ownership meta - never taken from the request body,
     * same as PurchaseOrderRepositoryInterface::create()'s $branchId.
     */
    public function create(
        int $fromProductId,
        int $toProductId,
        int $quantity,
        ?int $fromBranchId,
        ?int $toBranchId,
        ?string $note,
        int $requestedByUserId
    ): StockTransfer;

    public function find(int $id): ?StockTransfer;

    /**
     * $branchId === false (varsayılan): filtre yok, her depo. $branchId
     * === null: yalnızca Genel Merkez'in ya KAYNAK ya HEDEF olduğu
     * transferler. $branchId === int: yalnızca o şubenin ya kaynak ya
     * hedef olduğu transferler - bir depo hem giden hem gelen transferle
     * ilgilenir, PurchaseOrderRepositoryInterface::all()'ın aksine (bir
     * satın alma siparişinin tek bir depo taraf), burada İKİ taraf da
     * "kendi" transferi sayılır.
     *
     * @return list<StockTransfer>
     */
    public function all(?StockTransferStatus $status = null, int|false|null $branchId = false): array;

    /** pending -> completed. */
    public function complete(int $id, int $completedByUserId): void;

    /** pending -> cancelled. */
    public function cancel(int $id): void;
}
