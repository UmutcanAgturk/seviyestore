<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

enum StockMovementType: string
{
    /** A purchase order was (partially or fully) received. */
    case PURCHASE_IN = 'purchase_in';

    /** A stock count (bölüm 07 - faz 2) applied a variance. */
    case COUNT_ADJUSTMENT = 'count_adjustment';

    /** A depo görevlisi corrected the stock number by hand, outside a PO or count. */
    case MANUAL_ADJUSTMENT = 'manual_adjustment';

    /** A customer/branch return added stock back. */
    case RETURN_IN = 'return_in';

    /** Faz 4: a stock transfer left this depo for another (see StockTransfer). */
    case TRANSFER_OUT = 'transfer_out';

    /** Faz 4: a stock transfer arrived at this depo from another. */
    case TRANSFER_IN = 'transfer_in';
}
