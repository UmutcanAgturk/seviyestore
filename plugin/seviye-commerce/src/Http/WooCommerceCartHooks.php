<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Support\CartPricingService;
use Seviye\Students\Contracts\StudentLookupInterface;
use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Thin WooCommerce hook adapter - every actual decision is delegated to
 * {@see CartPricingService} (pure PHP, unit tested) or
 * {@see StudentLookupInterface}. This class itself is not unit tested, same
 * as every other WordPress/WooCommerce-touching adapter in this codebase
 * (WpdbConnection, the theme's inc/*.php files) - see
 * docs/ARCHITECTURE.md, "Test stratejisi".
 *
 * A cart item is tagged with the chosen student via a `scp_student_id`
 * value in WooCommerce's own cart item data array (not a new database
 * table - WooCommerce already owns cart/order storage). That value survives
 * into the persisted order as `_scp_student_id` order item meta.
 */
final class WooCommerceCartHooks
{
    private const CART_ITEM_KEY = 'scp_student_id';
    private const ORDER_ITEM_META_KEY = '_scp_student_id';

    public function __construct(
        private readonly CartPricingService $pricing,
        private readonly StudentLookupInterface $students
    ) {
    }

    public function register(): void
    {
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateStudentSelection'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'attachStudentId'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'applyResolvedPrices'], 20);
        add_filter('woocommerce_get_item_data', [$this, 'displayStudentName'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'persistStudentId'], 10, 4);
    }

    public function validateStudentSelection(bool $passed, int $productId, int $quantity): bool
    {
        if (!$passed) {
            return false;
        }

        $studentId = $this->requestedStudentId();

        if ($studentId === null || !$this->pricing->isValidGuardian(get_current_user_id(), $studentId)) {
            wc_add_notice(__('Lütfen geçerli bir öğrenci seçin.', 'seviye-commerce'), 'error');

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $cartItemData
     * @return array<string, mixed>
     */
    public function attachStudentId(array $cartItemData, int $productId): array
    {
        $studentId = $this->requestedStudentId();

        if ($studentId !== null) {
            $cartItemData[self::CART_ITEM_KEY] = $studentId;
        }

        return $cartItemData;
    }

    public function applyResolvedPrices(WC_Cart $cart): void
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        foreach ($cart->get_cart() as $cartItem) {
            if (!isset($cartItem[self::CART_ITEM_KEY])) {
                continue;
            }

            $product = $cartItem['data'];
            $resolved = $this->pricing->resolvePriceForCartItem(
                (int) $cartItem[self::CART_ITEM_KEY],
                $product->get_id(),
                (float) $product->get_price()
            );

            $product->set_price($resolved->amount);
        }
    }

    /**
     * @param list<array<string, mixed>> $itemData
     * @param array<string, mixed> $cartItem
     * @return list<array<string, mixed>>
     */
    public function displayStudentName(array $itemData, array $cartItem): array
    {
        if (!isset($cartItem[self::CART_ITEM_KEY])) {
            return $itemData;
        }

        $summary = $this->students->find((int) $cartItem[self::CART_ITEM_KEY]);

        if ($summary === null) {
            return $itemData;
        }

        $itemData[] = [
            'name' => __('Öğrenci', 'seviye-commerce'),
            'value' => trim($summary->firstName . ' ' . $summary->lastName),
        ];

        return $itemData;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function persistStudentId(
        WC_Order_Item_Product $item,
        string $cartItemKey,
        array $values,
        WC_Order $order
    ): void {
        if (isset($values[self::CART_ITEM_KEY])) {
            $item->add_meta_data(self::ORDER_ITEM_META_KEY, (int) $values[self::CART_ITEM_KEY], true);
        }
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
