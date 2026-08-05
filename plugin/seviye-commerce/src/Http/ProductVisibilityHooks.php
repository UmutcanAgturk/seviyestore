<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Rbac\ProductCapability;
use Seviye\Commerce\Repository\ProductBranchVisibilityRepositoryInterface;
use Seviye\Commerce\Support\ProductOwnership;
use Seviye\Students\Contracts\ParentBranchLookupInterface;

/**
 * Enforces the shared catalog's per-branch active/passive toggle on the
 * storefront: a Veli sees (and may only add to cart) a product if it is
 * active for AT LEAST ONE of their own children's branches - the same
 * "any of my children's branches" reasoning CartPricingService already
 * uses for guardian checks. Staff (anyone holding
 * ProductCapability::MANAGE_PRODUCTS, i.e. the "Ürünler" panel) always see
 * everything, since hiding a product from the very people managing its
 * visibility would be self-defeating.
 *
 * "Bir şube kendi eklediği ürünü sadece o şube ve o şubenin öğrencileri
 * görebilecek" - layered IN FRONT of the toggle above: a branch-owned
 * product (see ProductOwnership) is a hard gate, not an opt-out default -
 * it is invisible to every Veli whose children are all in OTHER branches,
 * regardless of that product's own active/passive toggle state. A
 * Genel Merkez-owned product (no owner branch) is unaffected by this gate
 * and keeps the exact opt-out behaviour every product already had before
 * ownership existed.
 */
final class ProductVisibilityHooks
{
    public function __construct(
        private readonly ProductBranchVisibilityRepositoryInterface $visibility,
        private readonly ParentBranchLookupInterface $parentBranches,
        private readonly ProductOwnership $ownership
    ) {
    }

    public function register(): void
    {
        add_filter('woocommerce_product_is_visible', [$this, 'filterVisibility'], 10, 2);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateActiveForBranch'], 5, 2);
    }

    public function filterVisibility(bool $visible, int $productId): bool
    {
        if (!$visible || $this->currentUserBypasses()) {
            return $visible;
        }

        return $this->isActiveForCurrentUser($productId);
    }

    public function validateActiveForBranch(bool $passed, int $productId): bool
    {
        if (!$passed || $this->currentUserBypasses()) {
            return $passed;
        }

        if ($this->isActiveForCurrentUser($productId)) {
            return true;
        }

        wc_add_notice(__('Bu ürün şu anda satın alınamıyor.', 'seviye-commerce'), 'error');

        return false;
    }

    private function currentUserBypasses(): bool
    {
        return !is_user_logged_in() || current_user_can(ProductCapability::MANAGE_PRODUCTS->value);
    }

    private function isActiveForCurrentUser(int $productId): bool
    {
        $branchIds = $this->parentBranches->branchIdsForParent(get_current_user_id());

        if ($branchIds === []) {
            // Not a parent with linked children (or a role this platform
            // has no branch-scoping opinion about) - never invented, so
            // don't hide anything the visibility model has no say over.
            return true;
        }

        $ownerBranchId = $this->ownership->ownerBranchId($productId);

        if ($ownerBranchId !== null && !in_array($ownerBranchId, $branchIds, true)) {
            return false;
        }

        foreach ($branchIds as $branchId) {
            if ($this->visibility->isActiveForBranch($productId, $branchId)) {
                return true;
            }
        }

        return false;
    }
}
