<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

/**
 * "Bir şube kendi eklediği ürünü sadece o şube ve o şubenin öğrencileri
 * görebilecek" - which branch (if any) created a given shared-catalog
 * product, stored as ordinary WooCommerce post meta rather than a new
 * table (products already stay entirely WooCommerce's own - see
 * ProductsRestController's own docblock). Absence of the meta key means
 * "Genel Merkez-owned" - the same default every product created before
 * this feature existed already has, so no backfill migration is needed:
 * an unset owner is indistinguishable from (and behaves exactly like) an
 * explicit HQ product.
 */
final class ProductOwnership
{
    private const META_KEY = '_scp_owner_branch_id';

    public function ownerBranchId(int $productId): ?int
    {
        $value = get_post_meta($productId, self::META_KEY, true);

        return $value !== '' && $value !== false ? (int) $value : null;
    }

    /**
     * @param ?int $branchId null clears the meta (marks the product
     *     Genel Merkez-owned again)
     */
    public function setOwnerBranchId(int $productId, ?int $branchId): void
    {
        if ($branchId === null) {
            delete_post_meta($productId, self::META_KEY);

            return;
        }

        update_post_meta($productId, self::META_KEY, $branchId);
    }
}
