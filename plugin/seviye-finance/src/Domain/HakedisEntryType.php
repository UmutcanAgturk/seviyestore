<?php

declare(strict_types=1);

namespace Seviye\Finance\Domain;

enum HakedisEntryType: string
{
    /** A branch earned this amount - fired when an order line item completes. */
    case EARNED = 'earned';

    /** A previously-earned amount is reversed - fired on a post-completion refund/cancellation. */
    case REVERSED = 'reversed';

    /**
     * "Kısmi iade -> hakediş orantılı ters kayıt": a FRACTION of a
     * previously-earned amount is reversed, proportional to how much of
     * the order a single partial refund covers. Unlike REVERSED (fires at
     * most once per order item - the order leaves `completed` for good),
     * this can fire multiple times for the same item, once per distinct
     * refund - see the refund_id column's docblock in
     * CreateHakedisEntriesTable.
     */
    case PARTIAL_REVERSAL = 'partial_reversal';
}
