<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

/**
 * PENDING -> (DISMISSED | CONVERTED), her ikisi de tek yönlü ve nihai -
 * bir öneri bir kez ele alındıktan sonra tekrar açılmaz; yeni bir düşük
 * stok olayı gelirse LowStockPurchaseSuggestionListener yeni bir PENDING
 * satırı açar (bkz. o sınıfın docblock'u).
 */
enum PurchaseSuggestionStatus: string
{
    case PENDING = 'pending';
    case DISMISSED = 'dismissed';
    case CONVERTED = 'converted';
}
