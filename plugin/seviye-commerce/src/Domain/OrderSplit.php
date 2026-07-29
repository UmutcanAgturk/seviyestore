<?php

declare(strict_types=1);

namespace Seviye\Commerce\Domain;

/**
 * How much of one order line item's price belongs to the branch versus
 * headquarters, per that branch's commission rate at the time of the order
 * (see {@see \Seviye\Commerce\Support\SplitPaymentCalculator}).
 */
final class OrderSplit
{
    public function __construct(
        public readonly float $branchShare,
        public readonly float $hqShare
    ) {
    }
}
