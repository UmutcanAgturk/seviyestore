<?php

declare(strict_types=1);

namespace Seviye\Depo\Contracts;

/**
 * Published contract for other modules reporting on purchase order history
 * (currently only Seviye Reports) - the boundary other modules are allowed
 * to depend on, never Depo's internal
 * Repository\PurchaseOrderRepositoryInterface/Domain\PurchaseOrder. See
 * docs/ARCHITECTURE.md, "Kural", and Commerce's identically-shaped
 * OrderLineItemQueryInterface.
 */
interface WarehouseReportQueryInterface
{
    /**
     * @return list<PurchaseOrderReportRecord>
     */
    public function search(PurchaseOrderReportFilter $filter): array;
}
