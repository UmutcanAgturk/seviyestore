<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use Seviye\Depo\Domain\PurchaseOrder;
use Seviye\Depo\Domain\PurchaseOrderItem;
use Seviye\Depo\Domain\PurchaseOrderStatus;

interface PurchaseOrderRepositoryInterface
{
    /**
     * @param list<array{product_id: int, quantity_ordered: int, unit_cost: ?float}> $items
     * @param ?int $branchId hangi deponun siparişi - NULL Genel Merkez
     *     deposu, bir değer o şubenin kendi deposu (bkz.
     *     CreatePurchaseOrdersTable'ın branch_id sütun docblock'u).
     *     Çağıran (PurchaseOrdersRestController) her kalemin ürün
     *     sahipliğini bu değerle eşleştiğini ÖNCEDEN doğrulamış olmalı -
     *     repository katmanı bunu bir kez daha kontrol etmez.
     */
    public function create(
        int $supplierId,
        ?string $expectedDate,
        ?string $note,
        int $createdByUserId,
        array $items,
        ?int $branchId = null
    ): PurchaseOrder;

    public function find(int $id): ?PurchaseOrder;

    /**
     * $branchId === false (varsayılan): filtre yok, her depo. $branchId
     * === null: yalnızca Genel Merkez deposu. $branchId === int: yalnızca
     * o şubenin deposu. (İki farklı "boş" durumu - "filtre yok" ile
     * "yalnızca Genel Merkez" - ayırt etmek için false sentinel'i
     * kullanılıyor, aynı desen ProductsRestController'ın branch filtresinde
     * de yok çünkü orada "tüm şubeler" zaten ayrı bir kapsam değil; burada
     * ise Genel Merkez'in KENDİSİ de bir depo kapsamı olduğundan null'u
     * "filtre yok" için kullanamıyoruz.)
     *
     * @return list<PurchaseOrder>
     */
    public function all(?PurchaseOrderStatus $status = null, ?int $supplierId = null, int|false|null $branchId = false): array;

    /** draft -> sent. */
    public function send(int $id): void;

    public function cancel(int $id): void;

    /**
     * "Tedarikçi portalı" - tedarikçinin kendi bildirdiği kargo/gönderim
     * bilgisi. Yalnızca bilgilendirme amaçlıdır, status'u DEĞİŞTİRMEZ ve
     * okulun kendi mal kabul akışından (receiveItem()) tamamen ayrıdır -
     * bkz. CreatePurchaseOrdersTable'ın supplier_shipped_at docblock'u.
     */
    public function markShipped(int $id): void;

    /**
     * Increments the item's quantity_received by $quantity, then
     * recalculates and persists the parent purchase order's status
     * (sent -> partially_received -> completed, see
     * {@see \Seviye\Depo\Domain\PurchaseOrderStatus}).
     */
    public function receiveItem(int $itemId, int $quantity): PurchaseOrderItem;

    public function findItem(int $itemId): ?PurchaseOrderItem;
}
