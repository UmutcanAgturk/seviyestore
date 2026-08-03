<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Commerce\Http\Support\OrderPresenter;
use Seviye\Commerce\Rbac\OrderCapability;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Students\Contracts\StudentLookupInterface;
use WC_Order;
use WC_Order_Item_Product;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/orders - the admin-facing counterpart to
 * OrdersRestController's self-service /mine: Genel Merkez/Bölge Müdürü
 * (VIEW_ORDERS) browse every order platform-wide, Şube Müdürü
 * (VIEW_OWN_BRANCH_ORDERS) only orders touching their own branch's
 * students. Scoping mirrors Seviye\Reports\Http\ReportsRestController
 * exactly (see canViewOrders()).
 *
 * Reads directly from wc_get_orders() + each item's `_scp_student_id` meta
 * (resolved to a branch via StudentLookupInterface), exactly like
 * OrdersRestController's /mine - NOT through the derived
 * scp_order_line_items table (an earlier version of this controller did,
 * via OrderLineItemQueryInterface). That table is written by
 * OrderPersistenceHooks as a side effect of checkout and only ever kept in
 * sync going forward; any gap in it (a row that failed to write, a student/
 * branch that no longer resolves, an order placed through a path that
 * never fired the hook) silently hid a real, paid-for order from HQ/Şube
 * Müdürü - "velilerden gelen siparişleri geçmişe dönük olarak göremiyorum"
 * was exactly this. Reading wc_get_orders() directly means every order
 * WooCommerce itself knows about is visible, with no secondary cache that
 * can drift out of sync with the truth.
 *
 * A branch-scoped viewer only ever sees their OWN branch's line items
 * within a matched order, never another branch's - even on the rare order
 * that spans two branches' students - independent of whatever
 * product/date/student filter was applied to select the order in the first
 * place (see visibleItemIds()). HQ sees every item on every matched order,
 * unrestricted.
 */
final class AdminOrdersRestController extends AbstractRestController
{
    private const STUDENT_META_KEY = '_scp_student_id';

    /**
     * A completed order's line items have already fired hakediş (see
     * OrderPersistenceHooks::HAKEDIS_TRIGGER_STATUS) - cancelling it outright
     * would leave the branch credited for commission on money never
     * actually returned, so a completed order can only be REFUNDED, never
     * cancelled. Every other status precedes that point (or is a dead end -
     * failed), so no money has moved yet and a plain status change is safe.
     */
    private const CANCELLABLE_STATUSES = ['pending', 'processing', 'on-hold', 'failed'];

    public function __construct(
        private readonly StudentLookupInterface $students,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly OrderPresenter $presenter
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => [$this, 'canViewOrders'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/orders/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel'],
            'permission_callback' => [$this, 'canCancelOrder'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/orders/(?P<id>\d+)/refund', [
            'methods' => 'POST',
            'callback' => [$this, 'refund'],
            'permission_callback' => $this->requireCapability(OrderCapability::REFUND_ORDERS->value),
        ]);
    }

    public function canViewOrders(): bool
    {
        if (current_user_can(OrderCapability::VIEW_ORDERS->value)) {
            return true;
        }

        if (!current_user_can(OrderCapability::VIEW_OWN_BRANCH_ORDERS->value)) {
            return false;
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id()) !== null;
    }

    /**
     * Same two-tier check as canViewOrders(), plus (for the branch-scoped
     * tier only) an object-level check that THIS order actually touches the
     * caller's own branch - reuses matches() with no product/student filter,
     * the same helper index() uses to decide which orders even appear in a
     * branch-scoped listing.
     */
    public function canCancelOrder(WP_REST_Request $request): bool
    {
        if (current_user_can(OrderCapability::CANCEL_ORDERS->value)) {
            return true;
        }

        if (!current_user_can(OrderCapability::CANCEL_OWN_BRANCH_ORDERS->value)) {
            return false;
        }

        $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        if ($ownBranchId === null) {
            return false;
        }

        $order = wc_get_order((int) $request->get_param('id'));

        return $order instanceof WC_Order && $this->matches($order, $ownBranchId, null, null);
    }

    public function cancel(WP_REST_Request $request): WP_REST_Response
    {
        $order = wc_get_order((int) $request->get_param('id'));

        if (!$order instanceof WC_Order) {
            return new WP_REST_Response(['message' => __('Sipariş bulunamadı.', 'seviye-commerce')], 404);
        }

        if (!in_array($order->get_status(), self::CANCELLABLE_STATUSES, true)) {
            $message = __(
                'Yalnızca tamamlanmamış bir sipariş iptal edilebilir; tamamlanmış siparişte iade kullanın.',
                'seviye-commerce'
            );

            return new WP_REST_Response(['message' => $message], 422);
        }

        $reason = trim((string) ($request->get_param('reason') ?? ''));
        // update_status() both changes the status AND records $reason as an
        // order note in one call - fires woocommerce_order_status_changed,
        // which OrderPersistenceHooks::syncOrderStatus() already reacts to
        // (keeps scp_order_line_items in sync; no hakediş reversal needed
        // here since a cancellable order never reached HAKEDIS_TRIGGER_STATUS)
        // and which also dispatches `commerce.order_cancelled` for
        // Seviye Notifications to email the veli.
        $order->update_status('cancelled', $reason);

        return new WP_REST_Response($this->presenter->present($order, null, true));
    }

    /**
     * Full or partial refund of an already-completed (paid) order. Money is
     * never actually moved through a payment gateway here
     * (`refund_payment => false`) - every payment method this platform
     * supports (banka havalesi/nakit) was handled manually outside
     * WooCommerce to begin with, so a gateway refund call would have
     * nothing to refund against; this only records the refund as
     * bookkeeping (adjusts the order's refunded total, and - for a FULL
     * refund - transitions the order to `refunded`, which
     * OrderPersistenceHooks::syncOrderStatus() reacts to by reversing the
     * hakediş already credited for it). A PARTIAL refund does not go
     * through that same transition - WooCommerce itself does not demote a
     * partially-refunded order's status off `completed` - so
     * OrderPersistenceHooks::onOrderRefunded() (WooCommerce's own
     * `woocommerce_order_refunded` hook, which fires for both full and
     * partial refunds) is what fires the proportional hakediş reversal
     * instead; see that method's own docblock.
     *
     * `restock_items => false` - a refund here does not imply a physical
     * return; if stock genuinely needs restoring, that is Depo's own
     * inbound flow to record explicitly, not an automatic side effect of a
     * bookkeeping refund.
     */
    public function refund(WP_REST_Request $request): WP_REST_Response
    {
        $order = wc_get_order((int) $request->get_param('id'));

        if (!$order instanceof WC_Order) {
            return new WP_REST_Response(['message' => __('Sipariş bulunamadı.', 'seviye-commerce')], 404);
        }

        if ($order->get_status() !== 'completed') {
            $message = __(
                'Yalnızca tamamlanmış bir sipariş için iade yapılabilir; tamamlanmamış siparişte iptal kullanın.',
                'seviye-commerce'
            );

            return new WP_REST_Response(['message' => $message], 422);
        }

        $remaining = (float) $order->get_remaining_refund_amount();

        if ($remaining <= 0.0) {
            $message = __('Bu sipariş için iade edilebilecek bir tutar kalmadı.', 'seviye-commerce');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $requestedAmount = $request->get_param('amount');
        $amount = $requestedAmount === null || $requestedAmount === '' ? $remaining : (float) $requestedAmount;

        if ($amount <= 0.0 || $amount > $remaining + 0.01) {
            $message = sprintf(
                /* translators: %s: maximum refundable amount, formatted with thousand/decimal separators */
                __('İade tutarı 0 TRY\'den büyük ve en fazla %s TRY olabilir.', 'seviye-commerce'),
                number_format($remaining, 2, ',', '.')
            );

            return new WP_REST_Response(['message' => $message], 422);
        }

        $reason = trim((string) ($request->get_param('reason') ?? ''));

        $refund = wc_create_refund([
            'order_id' => $order->get_id(),
            'amount' => $amount,
            'reason' => $reason,
            'refund_payment' => false,
            'restock_items' => false,
        ]);

        if (is_wp_error($refund)) {
            return new WP_REST_Response(['message' => $refund->get_error_message()], 422);
        }

        $refreshedOrder = wc_get_order($order->get_id());

        return new WP_REST_Response(
            $this->presenter->present($refreshedOrder instanceof WC_Order ? $refreshedOrder : $order, null, true)
        );
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $isHq = current_user_can(OrderCapability::VIEW_ORDERS->value);
        $requestedBranchId = $isHq
            ? $this->intParam($request, 'branch_id')
            : $this->branchMemberships->branchIdForUser(get_current_user_id());
        $ownBranchId = $isHq ? null : $requestedBranchId;

        $args = ['limit' => -1, 'orderby' => 'date', 'order' => 'DESC'];
        $status = $this->stringParam($request, 'status');

        if ($status !== null) {
            $args['status'] = $status;
        }

        $productId = $this->intParam($request, 'product_id');
        $studentId = $this->intParam($request, 'student_id');
        $fromDate = $this->stringParam($request, 'from');
        $toDate = $this->stringParam($request, 'to');

        $presented = [];

        foreach (wc_get_orders($args) as $order) {
            if (!$order instanceof WC_Order || !$this->matchesDateRange($order, $fromDate, $toDate)) {
                continue;
            }

            if (!$this->matches($order, $requestedBranchId, $productId, $studentId)) {
                continue;
            }

            $presented[] = $this->presenter->present($order, $this->visibleItemIds($order, $ownBranchId), true);
        }

        $search = $this->stringParam($request, 'search');

        if ($search !== null) {
            $presented = array_values(array_filter(
                $presented,
                static fn (array $order): bool => self::matchesSearch($order, $search)
            ));
        }

        return new WP_REST_Response($presented);
    }

    private function matchesDateRange(WC_Order $order, ?string $from, ?string $to): bool
    {
        if ($from === null && $to === null) {
            return true;
        }

        $createdAt = $order->get_date_created();

        if ($createdAt === null) {
            return false;
        }

        $date = $createdAt->date('Y-m-d');

        if ($from !== null && $date < $from) {
            return false;
        }

        return $to === null || $date <= $to;
    }

    /**
     * At least one Seviye-tagged line item on the order must satisfy every
     * given filter for the order itself to match - branch/product/student
     * are evaluated per item since a single order can span multiple
     * students (and so, rarely, multiple branches).
     */
    private function matches(WC_Order $order, ?int $branchId, ?int $productId, ?int $studentId): bool
    {
        foreach ($order->get_items() as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $itemStudentId = (int) $item->get_meta(self::STUDENT_META_KEY);

            if ($itemStudentId <= 0) {
                continue;
            }

            if ($studentId !== null && $itemStudentId !== $studentId) {
                continue;
            }

            if ($productId !== null && $item->get_product_id() !== $productId) {
                continue;
            }

            if ($branchId !== null && $this->branchFor($itemStudentId) !== $branchId) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @return array<int, bool>|null null shows every item (HQ); a
     *     branch-scoped viewer's set is always derived from `$ownBranchId`
     *     alone, never narrowed further by product/date/student filters -
     *     those only decide which ORDERS appear (see matches()).
     */
    private function visibleItemIds(WC_Order $order, ?int $ownBranchId): ?array
    {
        if ($ownBranchId === null) {
            return null;
        }

        $ids = [];

        foreach ($order->get_items() as $itemId => $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $studentId = (int) $item->get_meta(self::STUDENT_META_KEY);

            if ($studentId > 0 && $this->branchFor($studentId) === $ownBranchId) {
                $ids[$itemId] = true;
            }
        }

        return $ids;
    }

    private function branchFor(int $studentId): ?int
    {
        return $this->students->find($studentId)?->branchId;
    }

    /**
     * @param array<string, mixed> $order
     */
    private static function matchesSearch(array $order, string $search): bool
    {
        $needle = mb_strtolower($search);
        $haystacks = [
            (string) $order['number'],
            (string) ($order['customer_name'] ?? ''),
            (string) ($order['customer_email'] ?? ''),
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains(mb_strtolower($haystack), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function intParam(WP_REST_Request $request, string $name): ?int
    {
        $value = $request->get_param($name);

        return $value === null || $value === '' ? null : (int) $value;
    }

    private function stringParam(WP_REST_Request $request, string $name): ?string
    {
        $value = $request->get_param($name);

        return $value === null || $value === '' ? null : (string) $value;
    }
}
