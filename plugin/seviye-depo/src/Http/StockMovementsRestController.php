<?php

declare(strict_types=1);

namespace Seviye\Depo\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Depo\Domain\StockMovement;
use Seviye\Depo\Domain\StockMovementType;
use Seviye\Depo\Rbac\WarehouseCapability;
use Seviye\Depo\Repository\StockMovementRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/depo/stock-movements - salt okunur defter görüntüleme. Yazma
 * bu uç noktadan asla yapılmaz; her satır PurchaseOrdersRestController::receive()
 * (ya da faz 2'de stok sayımı) tarafından dolaylı olarak üretilir.
 *
 * Faz 4: VIEW_STOCK_MOVEMENTS (platform-wide) her depoyu görür,
 * VIEW_OWN_BRANCH_STOCK_MOVEMENTS yalnızca kendi şubesinin defterini -
 * bkz. PurchaseOrdersRestController'ın resolveBranchScope() docblock'u
 * (aynı desen).
 */
final class StockMovementsRestController extends AbstractRestController
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $movements,
        private readonly BranchLookupInterface $branches,
        private readonly BranchMembershipInterface $branchMemberships
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/stock-movements', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => [$this, 'canViewStockMovements'],
        ]);
    }

    public function canViewStockMovements(): bool
    {
        return current_user_can(WarehouseCapability::VIEW_STOCK_MOVEMENTS->value)
            || current_user_can(WarehouseCapability::VIEW_OWN_BRANCH_STOCK_MOVEMENTS->value);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $productId = $this->intParam($request, 'product_id');
        $type = StockMovementType::tryFrom((string) ($request->get_param('type') ?? ''));
        $from = $this->stringParam($request, 'from');
        $to = $this->stringParam($request, 'to');
        $branchScope = $this->resolveBranchScope($request);

        $movements = array_map(
            $this->serialize(...),
            $this->movements->list($productId, $type, $from, $to, $branchScope)
        );

        return new WP_REST_Response($movements);
    }

    private function resolveBranchScope(WP_REST_Request $request): int|false|null
    {
        if (current_user_can(WarehouseCapability::VIEW_STOCK_MOVEMENTS->value)) {
            $raw = $request->get_param('branch_id');

            if ($raw === null || $raw === '') {
                return false;
            }

            return $raw === 'hq' ? null : (int) $raw;
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(StockMovement $movement): array
    {
        $branch = $movement->branchId !== null ? $this->branches->find($movement->branchId) : null;

        return [
            'id' => $movement->id,
            'product_id' => $movement->productId,
            'type' => $movement->type->value,
            'quantity_delta' => $movement->quantityDelta,
            'reference_type' => $movement->referenceType,
            'reference_id' => $movement->referenceId,
            'note' => $movement->note,
            'created_by' => $movement->createdByUserId,
            'created_at' => $movement->createdAt,
            'branch_id' => $movement->branchId,
            'branch_name' => $branch?->name ?? __('Genel Merkez', 'seviye-depo'),
        ];
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
