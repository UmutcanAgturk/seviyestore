<?php

declare(strict_types=1);

namespace Seviye\Commerce\Contracts;

/**
 * Published contract for other modules reporting on order line item
 * history (currently only Seviye Reports) - the boundary other modules are
 * allowed to depend on, never Commerce's internal
 * Repository\OrderLineItemRepositoryInterface/Domain\OrderLineItem. See
 * docs/ARCHITECTURE.md, "Kural".
 */
interface OrderLineItemQueryInterface
{
    /**
     * @return list<OrderLineItemRecord>
     */
    public function search(OrderLineItemFilter $filter): array;
}
