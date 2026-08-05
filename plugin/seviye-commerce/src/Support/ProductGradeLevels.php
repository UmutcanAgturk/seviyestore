<?php

declare(strict_types=1);

namespace Seviye\Commerce\Support;

/**
 * "Yeni ürün eklerken ürünlerin hangi sınıf ya da sınıflardaki öğrencilere
 * görüneceğini belirten bir filtre koy" - which grade-level labels (e.g.
 * "5. Sınıf", stored the same way Students stores class_name - see
 * theme's scp_grade_level_options()) a product is restricted to, stored as
 * ordinary WooCommerce post meta rather than a new table - mirrors
 * ProductOwnership's own reasoning exactly. Absence of the meta key (or an
 * empty array) means "visible to every grade level", the same
 * backward-compatible default every product created before this feature
 * existed already behaves as: no backfill migration needed.
 */
final class ProductGradeLevels
{
    private const META_KEY = '_scp_product_grade_levels';

    /**
     * @return list<string>
     */
    public function gradeLevelsFor(int $productId): array
    {
        $value = get_post_meta($productId, self::META_KEY, true);

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }

    /**
     * @param list<string> $gradeLevels empty clears the meta (marks the
     *     product visible to every grade level again)
     */
    public function setGradeLevelsFor(int $productId, array $gradeLevels): void
    {
        if ($gradeLevels === []) {
            delete_post_meta($productId, self::META_KEY);

            return;
        }

        update_post_meta($productId, self::META_KEY, array_values($gradeLevels));
    }
}
