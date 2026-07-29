<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

use Seviye\Commerce\Domain\OrderSplit;

/**
 * Pure arithmetic, deliberately isolated from Http\OrderPersistenceHooks for
 * the same reason Support\CartPricingService is isolated from
 * Http\WooCommerceCartHooks: it is the one piece of real decision logic in
 * this flow, worth testing directly.
 *
 * commissionRate is "the branch's share of an order" - already established
 * by Branches' own Domain\CommissionRate docblock when that value object
 * was built - so branchShare is commissionRate% of price, and hqShare is
 * the remainder. Rounds to 2 decimals (this platform's storage precision
 * for money, matching scp_order_line_items.price/scp_price_rules.price).
 */
final class SplitPaymentCalculator
{
    public function calculate(float $price, float $commissionRate): OrderSplit
    {
        $branchShare = round($price * $commissionRate / 100, 2);
        $hqShare = round($price - $branchShare, 2);

        return new OrderSplit($branchShare, $hqShare);
    }
}
