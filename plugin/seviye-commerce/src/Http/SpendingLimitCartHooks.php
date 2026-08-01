<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Repository\SpendingLimitRepositoryInterface;
use Seviye\Commerce\Support\StudentSpendingCalculator;
use WC_Product;

/**
 * "Öğrenci/veli bazlı harcama limiti... limit aşılınca sepette uyarı" -
 * hard-blocks the add-to-cart action (same wc_add_notice()+return false
 * pattern as WooCommerceCartHooks::validateStudentSelection() and
 * ProductVisibilityHooks::validateActiveForBranch()) once the student's
 * confirmed spend (StudentSpendingCalculator, reading paid WooCommerce
 * orders) plus whatever of theirs is already in the cart plus this addition
 * would exceed their configured limit.
 *
 * Registered on the SAME `woocommerce_add_to_cart_validation` filter as
 * WooCommerceCartHooks, at a later priority (20 vs. 10) so it only runs once
 * a student has actually been validated as selected; a $passed of false
 * coming in (no/invalid student) is left untouched.
 */
final class SpendingLimitCartHooks
{
    private const CART_ITEM_KEY = 'scp_student_id';

    public function __construct(
        private readonly SpendingLimitRepositoryInterface $limits,
        private readonly StudentSpendingCalculator $spending
    ) {
    }

    public function register(): void
    {
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateSpendingLimit'], 20, 3);
    }

    public function validateSpendingLimit(bool $passed, int $productId, int $quantity): bool
    {
        if (!$passed) {
            return false;
        }

        $studentId = $this->requestedStudentId();

        if ($studentId === null) {
            return true;
        }

        $limit = $this->limits->find($studentId);

        if ($limit === null) {
            return true;
        }

        $product = wc_get_product($productId);

        if (!$product instanceof WC_Product) {
            return true;
        }

        $spent = $this->spending->spentAmount($studentId, $limit->period);
        $cartTotal = $this->cartTotalForStudent($studentId);
        $additional = (float) $product->get_price() * $quantity;
        $remaining = $limit->limitAmount - $spent - $cartTotal;

        if ($additional > $remaining) {
            wc_add_notice(
                sprintf(
                    /* translators: %s: remaining spending limit amount, formatted as currency */
                    __('Öğrencinin harcama limiti yetersiz. Kalan limit: %s', 'seviye-commerce'),
                    wc_price(max($remaining, 0.0))
                ),
                'error'
            );

            return false;
        }

        return true;
    }

    private function cartTotalForStudent(int $studentId): float
    {
        if (!function_exists('WC') || WC()->cart === null) {
            return 0.0;
        }

        $total = 0.0;

        foreach (WC()->cart->get_cart() as $cartItem) {
            if ((int) ($cartItem[self::CART_ITEM_KEY] ?? 0) !== $studentId) {
                continue;
            }

            $product = $cartItem['data'] ?? null;

            if ($product instanceof WC_Product) {
                $total += (float) $product->get_price() * (int) $cartItem['quantity'];
            }
        }

        return $total;
    }

    private function requestedStudentId(): ?int
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce's own add-to-cart nonce is verified upstream by WC_Form_Handler::add_to_cart_action() before this filter/hook chain fires.
        if (!isset($_POST[self::CART_ITEM_KEY])) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
        $studentId = (int) wp_unslash($_POST[self::CART_ITEM_KEY]);

        return $studentId > 0 ? $studentId : null;
    }
}
