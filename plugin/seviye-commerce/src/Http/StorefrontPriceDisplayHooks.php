<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Pricing\Contracts\PriceResolverInterface;
use Seviye\Pricing\Contracts\PriceSource;
use Seviye\Students\Contracts\ParentBranchLookupInterface;
use WC_Product;
use WC_Product_Simple;

/**
 * "Şube ürüne özel bir fiyat verdiğinde mağaza kısmında da o fiyat dinamik
 * olarak değişecek" - until now PriceResolverInterface was only consulted
 * at cart time (see CartPricingService/WooCommerceCartHooks), so a
 * branch-specific price rule was invisible on the shop/product page itself;
 * a Veli only discovered it after adding the item to their cart. This hooks
 * the same resolver into WooCommerce's own price display pipeline
 * (`woocommerce_product_get_price`, which `get_price_html()` - and every
 * shop-loop/single-product template - already calls through).
 *
 * No specific student is known yet while merely browsing (that is only
 * chosen at add-to-cart time), so this can only resolve by BRANCH, not
 * STUDENT - the more specific per-student price (if any) still applies
 * correctly once an item is actually added to the cart, unaffected by what
 * this class shows beforehand. A Veli with children in more than one branch
 * sees the CHEAPEST of their branches' prices while browsing - a "starting
 * from" preview, not a promise; CartPricingService remains the sole
 * authority on the price actually charged.
 *
 * Deliberately excluded from cart/checkout/AJAX cart-fragment requests
 * (`is_cart()`/`is_checkout()`/`DOING_AJAX`) - WooCommerceCartHooks already
 * owns pricing there via `$product->set_price()` at
 * `woocommerce_before_calculate_totals`, which is student-aware; this class
 * re-reading `get_price()` after that would only re-resolve at the coarser
 * branch level and could clobber a correctly-resolved student price on a
 * later re-render.
 */
final class StorefrontPriceDisplayHooks
{
    public function __construct(
        private readonly PriceResolverInterface $priceResolver,
        private readonly ParentBranchLookupInterface $parentBranches
    ) {
    }

    public function register(): void
    {
        add_filter('woocommerce_product_get_price', [$this, 'resolveDisplayPrice'], 20, 2);
    }

    public function resolveDisplayPrice(string $price, WC_Product $product): string
    {
        if ($price === '' || !$product instanceof WC_Product_Simple) {
            return $price;
        }

        if (is_cart() || is_checkout() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return $price;
        }

        $branchIds = $this->parentBranches->branchIdsForParent(get_current_user_id());

        if ($branchIds === []) {
            // Guest, staff, or a Veli with no linked children yet - never
            // invented, so the base price stands (same "don't hide/change
            // anything the model has no say over" rule ProductVisibilityHooks
            // already follows).
            return $price;
        }

        $fallback = (float) $price;
        $best = null;

        foreach ($branchIds as $branchId) {
            $resolved = $this->priceResolver->resolve($product->get_id(), null, $branchId, $fallback);

            if ($resolved->source === PriceSource::FALLBACK) {
                continue;
            }

            if ($best === null || $resolved->amount < $best) {
                $best = $resolved->amount;
            }
        }

        return $best === null ? $price : (string) $best;
    }
}
