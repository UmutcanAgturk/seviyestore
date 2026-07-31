<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Commerce\Http\Support\OrderPresenter;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WC_Order;
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
}
