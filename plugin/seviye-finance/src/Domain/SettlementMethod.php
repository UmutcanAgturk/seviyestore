<?php

declare(strict_types=1);

namespace Seviye\Finance\Domain;

/**
 * How a tahsilat (settlement/payout) actually moved - a plain informational
 * tag on the ledger row, not something any calculation branches on.
 */
enum SettlementMethod: string
{
    case BANK_TRANSFER = 'bank_transfer';
    case CASH = 'cash';
    case OTHER = 'other';
}
