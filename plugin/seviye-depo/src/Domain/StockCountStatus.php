<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

/**
 * OPEN -> COMPLETED is the only transition, always manual (bkz.
 * StockCountsRestController::complete()) - PurchaseOrderStatus'un aksine
 * burada hesaplanan bir ara durum yok, tek bir "tamamlandı" düğmesi var.
 */
enum StockCountStatus: string
{
    case OPEN = 'open';
    case COMPLETED = 'completed';
}
