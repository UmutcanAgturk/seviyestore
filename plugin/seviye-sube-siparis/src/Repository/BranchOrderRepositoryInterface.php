<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Repository;

use Seviye\SubeSiparis\Domain\BranchOrder;
use Seviye\SubeSiparis\Domain\BranchOrderItem;
use Seviye\SubeSiparis\Domain\BranchOrderStatus;

interface BranchOrderRepositoryInterface
{
    /**
     * @param list<array{product_id: int, quantity_requested: int}> $items
     */
    public function create(int $branchId, int $createdByUserId, ?string $note, array $items): BranchOrder;

    public function find(int $id): ?BranchOrder;

    /**
     * $branchId === false: no filter (every branch). === null|int: reserved
     * for a future "unassigned"/single-branch filter - currently only a
     * concrete branch id is ever passed (every order always belongs to
     * exactly one branch, there is no HQ-owned bucket the way Depo's
     * warehouses have one).
     *
     * @return list<BranchOrder>
     */
    public function all(?BranchOrderStatus $status = null, int|false|null $branchId = false): array;

    /**
     * Wholesale replace of this order's item list - callers only invoke
     * this while the order is still DRAFT (enforced by the REST layer, not
     * here, same division of responsibility as PurchaseOrdersRestController
     * vs WpdbPurchaseOrderRepository).
     *
     * @param list<array{product_id: int, quantity_requested: int}> $items
     */
    public function replaceItems(int $id, array $items): BranchOrder;

    public function submit(int $id): void;

    public function cancel(int $id): void;

    public function reject(int $id, string $reason): void;

    /**
     * Writes each item's locked-in free/paid split and unit price, then
     * moves the order to AWAITING_PAYMENT (some item has a paid portion) or
     * straight to COMPLETED (everything was covered by free quota) - see
     * Domain\BranchOrderStatus's own docblock for why there is no separate
     * "approved" state in between.
     *
     * @param array<int, array{free: int, paid: int, unit_price: ?float}> $splitsByItemId
     */
    public function approve(int $id, int $approvedByUserId, array $splitsByItemId): BranchOrder;

    public function attachWcOrder(int $id, int $wcOrderId): void;

    /**
     * AWAITING_PAYMENT -> COMPLETED once the linked WooCommerce order's
     * payment clears - called only from Http\WooCommercePaymentBridge.
     */
    public function markCompleted(int $id): void;

    public function findItem(int $itemId): ?BranchOrderItem;

    /**
     * Sum of free_quantity_applied across every order for this
     * (branch, product) pair whose split is already locked in
     * (AWAITING_PAYMENT or COMPLETED) - draft/submitted/rejected/cancelled
     * orders never counted, since their split was never computed or was
     * discarded. This is what Support\BranchOrderSplitCalculator's
     * "alreadyConsumed" argument is fed from at approve() time.
     */
    public function consumedFreeQuantity(int $branchId, int $productId): int;
}
