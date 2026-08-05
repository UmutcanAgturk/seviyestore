<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Support\ProductOwnership;
use WC_Product;
use WC_Product_Variable;

/**
 * Publishes a product's ownership/base-price to other plugins via
 * `apply_filters()` instead of a formal DI Contract + hard "Requires
 * Plugins" dependency - Seviye Commerce already depends on Seviye Pricing
 * (CartPricingService), so a Contract the other direction (Pricing reading
 * Commerce data) would make the two plugins depend on each other, an
 * activation-order/composer-path-repo cycle for no real benefit. The same
 * loosely-coupled filter-bridge reasoning Depo's `scp_depo_supplier_id_for_user`
 * uses for the tedarikçi portalı. Consumed by
 * Seviye\Pricing\Http\PricingRestController::violatesBasePriceFloor() -
 * when Commerce isn't active, `apply_filters()` simply returns the default
 * (null) and Pricing's floor check no-ops, exactly like today.
 */
final class ProductOwnershipBridge
{
    public function __construct(private readonly ProductOwnership $ownership)
    {
    }

    public function register(): void
    {
        add_filter('scp_commerce_product_owner_branch_id', [$this, 'ownerBranchId'], 10, 2);
        add_filter('scp_commerce_product_base_price', [$this, 'basePrice'], 10, 2);
    }

    public function ownerBranchId(?int $default, int $productId): ?int
    {
        return $this->ownership->ownerBranchId($productId) ?? $default;
    }

    /**
     * Variable products carry no price of their own - the lowest variation's
     * own regular price stands in as the floor reference, since that is the
     * cheapest a shopper could already buy the Genel Merkez product for.
     */
    public function basePrice(?float $default, int $productId): ?float
    {
        $product = wc_get_product($productId);

        if (!$product instanceof WC_Product) {
            return $default;
        }

        if ($product instanceof WC_Product_Variable) {
            $prices = array_map('floatval', $product->get_variation_prices('regular')['regular_price'] ?? []);

            return $prices === [] ? $default : min($prices);
        }

        $price = $product->get_regular_price();

        return $price === '' ? $default : (float) $price;
    }
}
