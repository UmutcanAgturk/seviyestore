<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

use Seviye\Pricing\Contracts\PriceResolverInterface;
use Seviye\Pricing\Contracts\ResolvedPrice;
use Seviye\Students\Contracts\StudentGuardianCheckInterface;

/**
 * The pure, WordPress/WooCommerce-independent decision core of the cart
 * integration - {@see \Seviye\Commerce\Http\WooCommerceCartHooks} is a thin
 * adapter that only calls into this class and WP/WC functions, exactly like
 * AuthService/WpRoleGateway in Seviye Security.
 *
 * The guardian check happens once, at add-to-cart time
 * ({@see isValidGuardian()}) - resolvePriceForCartItem() (called on every
 * cart totals recalculation) trusts the studentId already stored on the
 * cart item rather than re-checking guardianship each time. A guardian link
 * being revoked mid-session (rare, not a security boundary - the theme's
 * role/zone gate already keeps non-Veli users out of the storefront
 * entirely) would only leave a stale price in an existing cart line, not
 * expose another guardian's child.
 */
final class CartPricingService
{
    public function __construct(
        private readonly StudentGuardianCheckInterface $guardianCheck,
        private readonly PriceResolverInterface $priceResolver
    ) {
    }

    public function isValidGuardian(int $parentUserId, int $studentId): bool
    {
        return $this->guardianCheck->isGuardianOf($parentUserId, $studentId);
    }

    public function resolvePriceForCartItem(int $studentId, int $productId, float $wcDefaultPrice): ResolvedPrice
    {
        return $this->priceResolver->resolve($productId, $studentId, null, $wcDefaultPrice);
    }
}
