<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use Seviye\Depo\Domain\StockCount;
use Seviye\Depo\Domain\StockCountItem;
use Seviye\Depo\Domain\StockCountStatus;

interface StockCountRepositoryInterface
{
    /**
     * @param array<int, int> $productStockLevels productId => WC'den o anda
     *     okunan stok miktarı. Bu tablo WC'ye bağımlı olmadığından anlık
     *     görüntü, çağıran (StockCountsRestController) tarafından
     *     toplanır - PurchaseOrdersRestController::receive()'ın
     *     wc_update_product_stock()'u doğrudan Http katmanında çağırmasıyla
     *     aynı ilke: repository yalnızca kendi tablolarına dokunur.
     */
    public function open(array $productStockLevels, int $startedByUserId): StockCount;

    public function find(int $id): ?StockCount;

    /**
     * @return list<StockCount>
     */
    public function all(?StockCountStatus $status = null): array;

    public function setCountedQuantity(int $itemId, int $countedQuantity): StockCountItem;

    public function findItem(int $itemId): ?StockCountItem;

    /** open -> completed. Kalemlere dokunmaz - yalnızca başlığın durumunu kapatır. */
    public function complete(int $id, int $completedByUserId): StockCount;
}
