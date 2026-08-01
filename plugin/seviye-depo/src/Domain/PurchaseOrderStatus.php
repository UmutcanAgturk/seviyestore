<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

/**
 * DRAFT -> SENT is the only manual transition a caller makes directly
 * (see PurchaseOrderRepositoryInterface::send()); PARTIALLY_RECEIVED and
 * COMPLETED are never set directly - they are computed from item totals
 * whenever {@see PurchaseOrderRepositoryInterface::receiveItem()} runs,
 * see WpdbPurchaseOrderRepository::recalculateStatus().
 */
enum PurchaseOrderStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PARTIALLY_RECEIVED = 'partially_received';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
