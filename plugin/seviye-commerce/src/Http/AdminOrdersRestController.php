<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Commerce\Contracts\OrderLineItemFilter;
use Seviye\Commerce\Contracts\OrderLineItemQueryInterface;
use Seviye\Commerce\Contracts\OrderLineItemRecord;
use Seviye\Commerce\Http\Support\OrderPresenter;
use Seviye\Commerce\Rbac\OrderCapability;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WC_Order;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/orders - the admin-facing counterpart to
 * OrdersRestController's self-service /mine: Genel Merkez/Bölge Müdürü
 * (VIEW_ORDERS) browse every order platform-wide, Şube Müdürü
 * (VIEW_OWN_BRANCH_ORDERS) only orders touching their own branch's
 * students. Scoping mirrors Seviye\Reports\Http\ReportsRestController
 * exactly (see effectiveBranchId() there / canViewOrders() here).
 *
 * "Which orders match" is resolved through the already-published
 * OrderLineItemQueryInterface (scp_order_line_items - populated for EVERY
 * order at checkout regardless of its WooCommerce status, see
 * OrderPersistenceHooks::persistOrderLineItems()) rather than
 * wc_get_orders() directly: that table already carries branch_id/
 * product_id/status per line item, so it is the only place this
 * platform's branch/product/date/status filtering can happen without
 * re-deriving a branch from student data on every request.
 *
 * A branch-scoped viewer only ever sees their OWN branch's line items
 * within a matched order, never another branch's - even on the rare order
 * that spans two branches' students - independent of whatever
 * product/date/status/student filter was applied to select the order in
 * the first place (see visibleItemIdsByOrder()). HQ sees every item on
 * every matched order, unrestricted.
 */
final class AdminOrdersRestController extends AbstractRestController
{
    public function __construct(
        private readonly OrderLineItemQueryInterface $orderLineItems,
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

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $isHq = current_user_can(OrderCapability::VIEW_ORDERS->value);
        $branchId = $isHq
            ? $this->intParam($request, 'branch_id')
            : $this->branchMemberships->branchIdForUser(get_current_user_id());

        $records = $this->orderLineItems->search(new OrderLineItemFilter(
            branchId: $branchId,
            productId: $this->intParam($request, 'product_id'),
            fromDate: $this->stringParam($request, 'from'),
            toDate: $this->stringParam($request, 'to'),
            status: $this->stringParam($request, 'status')
        ));

        $studentId = $this->intParam($request, 'student_id');

        if ($studentId !== null) {
            $records = array_values(array_filter(
                $records,
                static fn (OrderLineItemRecord $r): bool => $r->studentId === $studentId
            ));
        }

        $orderIds = array_values(array_unique(array_map(
            static fn (OrderLineItemRecord $r): int => $r->orderId,
            $records
        )));

        $visibleItemIdsByOrder = $isHq || $branchId === null ? null : $this->visibleItemIdsByOrder($branchId);

        $orders = array_values(array_filter(array_map(
            static fn (int $id) => wc_get_order($id) ?: null,
            $orderIds
        )));

        usort($orders, static fn (WC_Order $a, WC_Order $b): int => $b->get_id() <=> $a->get_id());

        $presented = array_map(
            fn (WC_Order $order): array => $this->presenter->present(
                $order,
                $visibleItemIdsByOrder[$order->get_id()] ?? null,
                true
            ),
            $orders
        );

        $search = $this->stringParam($request, 'search');

        if ($search !== null) {
            $presented = array_values(array_filter(
                $presented,
                static fn (array $order): bool => self::matchesSearch($order, $search)
            ));
        }

        return new WP_REST_Response($presented);
    }

    /**
     * @return array<int, array<int, bool>> order id -> set of visible WC
     *     order-item ids, branch-only (no other filter applied) - so a
     *     product/date/status/student filter narrows which ORDERS appear,
     *     never which items a branch-scoped viewer sees within one that
     *     matched.
     */
    private function visibleItemIdsByOrder(int $branchId): array
    {
        $records = $this->orderLineItems->search(new OrderLineItemFilter(branchId: $branchId));
        $map = [];

        foreach ($records as $record) {
            $map[$record->orderId][$record->orderItemId] = true;
        }

        return $map;
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
