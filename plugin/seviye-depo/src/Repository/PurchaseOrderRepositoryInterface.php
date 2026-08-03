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
     */
    public function create(
        int $supplierId,
        ?string $expectedDate,
        ?string $note,
        int $createdByUserId,
        array $items
    ): PurchaseOrder;

    public function find(int $id): ?PurchaseOrder;

    /**
     * @return list<PurchaseOrder>
     */
    public function all(?PurchaseOrderStatus $status = null, ?int $supplierId = null): array;

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
