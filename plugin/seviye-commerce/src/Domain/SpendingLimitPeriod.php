<?php

declare(strict_types=1);

namespace Seviye\Commerce\Domain;

/**
 * TERM ("dönemlik") is deliberately unbounded/cumulative (all paid orders
 * for the student, no start-date cutoff) rather than trying to derive
 * Turkish school-term (dönem) calendar boundaries, which this platform has
 * no source of truth for. MONTHLY resets at the start of each calendar
 * month. See {@see \Seviye\Commerce\Support\StudentSpendingCalculator}.
 */
enum SpendingLimitPeriod: string
{
    case MONTHLY = 'monthly';
    case TERM = 'term';
}
