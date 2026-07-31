<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Students\Contracts\StudentLookupInterface;
use WC_Order;
use WC_Order_Item_Product;
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
 * self-contained). The `_scp_student_id` line item meta
 * (WooCommerceCartHooks::persistStudentId()) is resolved to a name via
 * StudentLookupInterface so each purchased item shows which child it was
 * for.
 */
final class OrdersRestController extends AbstractRestController
{
    private const STUDENT_META_KEY = '_scp_student_id';

    public function __construct(private readonly StudentLookupInterface $students)
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

        return new WP_REST_Response(array_map($this->serializeOrder(...), $orders));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(WC_Order $order): array
    {
        $createdAt = $order->get_date_created();

        return [
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'date' => $createdAt !== null ? $createdAt->date('Y-m-d H:i') : null,
            'payment_method_title' => $order->get_payment_method_title(),
            'subtotal' => (float) $order->get_subtotal(),
            'total_tax' => (float) $order->get_total_tax(),
            'total' => (float) $order->get_total(),
            'items' => array_values(array_map($this->serializeItem(...), $order->get_items())),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(WC_Order_Item_Product $item): array
    {
        $studentId = (int) $item->get_meta(self::STUDENT_META_KEY);
        $student = $studentId > 0 ? $this->students->find($studentId) : null;
        $quantity = max(1, $item->get_quantity());

        return [
            'product_id' => $item->get_product_id(),
            'name' => $item->get_name(),
            'quantity' => $quantity,
            'unit_price' => (float) $item->get_total() / $quantity,
            'line_total' => (float) $item->get_total(),
            'line_tax' => (float) $item->get_total_tax(),
            'student_name' => $student !== null ? trim($student->firstName . ' ' . $student->lastName) : null,
        ];
    }
}
