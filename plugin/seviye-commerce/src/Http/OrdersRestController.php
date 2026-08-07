<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Http\Support\OrderPresenter;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WC_Order;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/orders/mine - self-service, authenticated only
 * (is_user_logged_in(), no RBAC capability), exactly mirroring
 * Seviye\Notifications\Http\NotificationsRestController: every Veli reads
 * their OWN past orders, this is not a permission-scoped resource. Scope
 * is enforced server-side via wc_get_orders(['customer_id' => ...]), never
 * by an id the client supplies - the same "server resolves scope" rule
 * every other *\/mine endpoint on this platform follows.
 *
 * Order/line-item data is read directly from WooCommerce's own WC_Order
 * API (not Seviye Commerce's own scp_order_line_items table, which exists
 * for hakediş/admin reporting scoped by branch/date range, not "one
 * customer's own order history" - re-deriving from WC here keeps this
 * self-contained) via {@see OrderPresenter}, shared with
 * AdminOrdersRestController's HQ/Şube Müdürü listing.
 *
 * "Velinin siparişlerim bölümünde ürünü iade et diye bir özellik yok" -
 * returnOrder() below is this same self-service surface's only WRITE route:
 * a Veli may return their OWN completed order (never anyone else's - id
 * ownership is checked against get_current_user_id(), the same "server
 * resolves scope, never a client-supplied id" rule mine() already follows)
 * within {@see OrderPresenter::canReturn()}'s 14-day window. Reuses
 * AdminOrdersRestController::refund()'s exact wc_create_refund() shape
 * (bookkeeping-only refund, no gateway call - see that method's own
 * docblock on why) rather than a parallel return workflow: WooCommerce's
 * native `woocommerce_order_refunded` hook that call triggers already fires
 * both the hakediş reversal AND the veli's "iade yapıldı" e-postası via
 * OrderPersistenceHooks::onOrderRefunded() / Notifications' own listener,
 * regardless of which controller invoked wc_create_refund() - so a
 * self-service return gets identical downstream bookkeeping to an
 * admin-recorded one for free, with zero duplicated logic.
 */
final class OrdersRestController extends AbstractRestController
{
    public function __construct(private readonly OrderPresenter $presenter)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/orders/mine', [
            'methods' => 'GET',
            'callback' => [$this, 'mine'],
            'permission_callback' => [$this, 'isLoggedIn'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/orders/mine/(?P<id>\d+)/return', [
            'methods' => 'POST',
            'callback' => [$this, 'returnOrder'],
            'permission_callback' => [$this, 'isLoggedIn'],
        ]);
    }

    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function mine(): WP_REST_Response
    {
        $orders = wc_get_orders([
            'customer_id' => get_current_user_id(),
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return new WP_REST_Response(array_map(
            fn (WC_Order $order): array => $this->presenter->present($order),
            $orders
        ));
    }

    public function returnOrder(WP_REST_Request $request): WP_REST_Response
    {
        $order = wc_get_order((int) $request->get_param('id'));

        // Same 404 for "doesn't exist" and "isn't yours" - a real order id
        // belonging to another veli must not be distinguishable from a
        // made-up one.
        if (!$order instanceof WC_Order || $order->get_customer_id() !== get_current_user_id()) {
            return new WP_REST_Response(['message' => __('Sipariş bulunamadı.', 'seviye-commerce')], 404);
        }

        if (!$this->presenter->canReturn($order)) {
            $message = __(
                'Bu sipariş için iade süresi dolmuş veya sipariş iadeye uygun değil.',
                'seviye-commerce'
            );

            return new WP_REST_Response(['message' => $message], 422);
        }

        $reason = trim((string) ($request->get_param('reason') ?? ''));

        $refund = wc_create_refund([
            'order_id' => $order->get_id(),
            'amount' => (float) $order->get_remaining_refund_amount(),
            'reason' => $reason !== '' ? $reason : __('Veli kendi siparişini iade etti.', 'seviye-commerce'),
            'refund_payment' => false,
            'restock_items' => false,
        ]);

        if (is_wp_error($refund)) {
            return new WP_REST_Response(['message' => $refund->get_error_message()], 422);
        }

        $refreshedOrder = wc_get_order($order->get_id());

        return new WP_REST_Response(
            $this->presenter->present($refreshedOrder instanceof WC_Order ? $refreshedOrder : $order)
        );
    }
}
