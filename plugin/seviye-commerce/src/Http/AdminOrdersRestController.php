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
