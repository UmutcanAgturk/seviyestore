<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Http;

use Seviye\SubeSiparis\Domain\BranchOrder;
use Seviye\SubeSiparis\Repository\BranchOrderRepositoryInterface;
use WC_Order;

/**
 * "Gerçek bir ödeme, kart ile" - bu modül KENDİ ödeme entegrasyonunu inşa
 * ETMEZ (bkz. docs/ARCHITECTURE.md, "Kural": WooCommerce ödemenin tek
 * doğruluk kaynağıdır). Aşan miktar için WooCommerce'in kendi
 * wc_create_order() API'siyle GERÇEK, ödenebilir bir WooCommerce siparişi
 * açılır ve şube müdürü WooCommerce'in kendi "Pay for order" sayfasına
 * (get_checkout_payment_url()) yönlendirilir - orada WooCommerce Ayarları'nda
 * etkin olan hangi ödeme yöntemi varsa (kart için iyzico/PayTR/Stripe vb.
 * bir WooCommerce ödeme ağ geçidi eklentisi) onunla öder. Bu köprü hiçbir
 * kart bilgisini görmez/işlemez, yalnızca siparişi oluşturur ve ödeme
 * tamamlandığında (woocommerce_order_status_changed) şube siparişini
 * COMPLETED olarak işaretler.
 */
final class WooCommercePaymentBridge
{
    private const BRANCH_ORDER_META_KEY = '_scp_branch_order_id';

    public function __construct(private readonly BranchOrderRepositoryInterface $branchOrders)
    {
    }

    public function register(): void
    {
        add_action('woocommerce_order_status_changed', [$this, 'syncBranchOrderStatus'], 10, 4);
    }

    /**
     * $order->hasPaidPortion() olduğu Http\BranchOrdersRestController::approve()
     * tarafından zaten doğrulanmış olmalı - burada tekrar kontrol edilmiyor,
     * ama yalnızca paidQuantity > 0 olan kalemler WooCommerce sipariş
     * kalemine dönüşüyor (ücretsiz kotadan karşılanan kalemler hiç
     * WooCommerce siparişine girmiyor).
     */
    public function createOrderForBranchOrder(BranchOrder $order): ?string
    {
        if (!function_exists('wc_create_order')) {
            return null;
        }

        $wcOrder = wc_create_order(['status' => 'pending']);

        if (!$wcOrder instanceof WC_Order) {
            return null;
        }

        foreach ($order->items as $item) {
            if (!$item->hasPaidPortion()) {
                continue;
            }

            $product = function_exists('wc_get_product') ? wc_get_product($item->productId) : false;

            if ($product === false || $product === null) {
                continue;
            }

            $wcOrder->add_product($product, $item->paidQuantity);
        }

        $wcOrder->update_meta_data(self::BRANCH_ORDER_META_KEY, $order->id);
        $wcOrder->set_customer_id($order->createdByUserId);
        $wcOrder->calculate_totals();
        $wcOrder->save();

        $this->branchOrders->attachWcOrder($order->id, $wcOrder->get_id());

        return $wcOrder->get_checkout_payment_url();
    }

    public function paymentUrlForBranchOrder(BranchOrder $order): ?string
    {
        if ($order->wcOrderId === null || !function_exists('wc_get_order')) {
            return null;
        }

        $wcOrder = wc_get_order($order->wcOrderId);

        if (!$wcOrder instanceof WC_Order) {
            return null;
        }

        return $wcOrder->get_checkout_payment_url();
    }

    public function syncBranchOrderStatus(int $orderId, string $oldStatus, string $newStatus, WC_Order $order): void
    {
        $branchOrderId = (int) $order->get_meta(self::BRANCH_ORDER_META_KEY, true);

        if ($branchOrderId <= 0) {
            return;
        }

        $paidStatuses = function_exists('wc_get_is_paid_statuses')
            ? wc_get_is_paid_statuses()
            : ['processing', 'completed'];

        if (in_array($newStatus, $paidStatuses, true)) {
            $this->branchOrders->markCompleted($branchOrderId);
        }
    }
}
