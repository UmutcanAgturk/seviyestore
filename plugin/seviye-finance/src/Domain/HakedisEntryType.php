<?php

declare(strict_types=1);

namespace Seviye\Finance\Domain;

enum HakedisEntryType: string
{
    /** A branch earned this amount - fired when an order line item completes. */
    case EARNED = 'earned';

    /** A previously-earned amount is reversed - fired on a post-completion refund/cancellation. */
    case REVERSED = 'reversed';
}
