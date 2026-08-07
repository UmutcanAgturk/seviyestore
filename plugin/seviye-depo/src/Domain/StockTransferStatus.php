<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

/**
 * PENDING -> COMPLETED or PENDING -> CANCELLED, always manual - mirrors
 * StockCountStatus's single-transition shape rather than
 * PurchaseOrderStatus's computed intermediate state (bkz.
 * StockTransfersRestController::complete()/cancel()). Stock only moves
 * (kaynaktan düşer, hedefe eklenir) when a transfer reaches COMPLETED -
 * see StockTransfer's own docblock for why this stays two-step rather
 * than applying immediately on creation.
 */
enum StockTransferStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
