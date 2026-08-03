<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use Seviye\Depo\Domain\Supplier;
use Seviye\Depo\Domain\SupplierStatus;

interface SupplierRepositoryInterface
{
    public function create(
        string $name,
        ?string $contactName,
        ?string $phone,
        ?string $email,
        ?string $taxNumber,
        ?string $address,
        ?int $userId = null
    ): Supplier;

    public function update(
        int $id,
        string $name,
        ?string $contactName,
        ?string $phone,
        ?string $email,
        ?string $taxNumber,
        ?string $address,
        SupplierStatus $status,
        ?int $userId = null
    ): Supplier;

    /**
     * @throws \RuntimeException if the supplier has purchase order history
     *     (scp_purchase_orders.supplier_id's FK is RESTRICT, not CASCADE -
     *     see CreatePurchaseOrdersTable).
     */
    public function delete(int $id): void;

    public function find(int $id): ?Supplier;

    /**
     * "Tedarikçi portalı" - the supplier linked to this WP user's account,
     * or null if this user has no supplier link (the overwhelming majority
     * of platform users - every non-portal role).
     */
    public function findByUserId(int $userId): ?Supplier;

    /**
     * @return list<Supplier>
     */
    public function all(): array;
}
