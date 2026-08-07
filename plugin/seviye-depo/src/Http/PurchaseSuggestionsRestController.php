<?php

declare(strict_types=1);

namespace Seviye\Depo\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Depo\Domain\PurchaseSuggestion;
use Seviye\Depo\Domain\PurchaseSuggestionStatus;
use Seviye\Depo\Rbac\WarehouseCapability;
use Seviye\Depo\Repository\PurchaseOrderRepositoryInterface;
use Seviye\Depo\Repository\PurchaseSuggestionRepositoryInterface;
use Seviye\Depo\Repository\SupplierRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/depo/purchase-suggestions/* - LowStockPurchaseSuggestionListener
 * tarafından açılan önerilerin görüntülenmesi, reddedilmesi ve gerçek bir
 * satın alma siparişine dönüştürülmesi. convert() yeni bir DRAFT PurchaseOrder
 * oluşturur (PurchaseOrderRepositoryInterface::create() üzerinden, tek
 * kalemli) - PurchaseOrdersRestController::store()'un aynı yolunu izler,
 * kod tekrarını önlemek için doğrudan repository'yi kullanır.
 *
 * Faz 4: MANAGE_PURCHASE_SUGGESTIONS (platform-wide) her depoyu görür,
 * MANAGE_OWN_BRANCH_PURCHASE_SUGGESTIONS yalnızca kendi şubesinin
 * önerilerini - bkz. PurchaseOrdersRestController'ın resolveBranchScope()
 * docblock'u (aynı desen). convert() önerinin branch_id'sini yeni açılan
 * PurchaseOrder'a aynen taşır.
 */
final class PurchaseSuggestionsRestController extends AbstractRestController
{
    public function __construct(
        private readonly PurchaseSuggestionRepositoryInterface $suggestions,
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly BranchLookupInterface $branches,
        private readonly BranchMembershipInterface $branchMemberships
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-suggestions', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-suggestions/(?P<id>\d+)/dismiss', [
            'methods' => 'POST',
            'callback' => [$this, 'dismiss'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-suggestions/(?P<id>\d+)/convert', [
            'methods' => 'POST',
            'callback' => [$this, 'convert'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);
    }

    public function canManageWarehouse(): bool
    {
        return current_user_can(WarehouseCapability::MANAGE_PURCHASE_SUGGESTIONS->value)
            || current_user_can(WarehouseCapability::MANAGE_OWN_BRANCH_PURCHASE_SUGGESTIONS->value);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $status = PurchaseSuggestionStatus::tryFrom((string) ($request->get_param('status') ?? ''));
        $branchScope = $this->resolveBranchScope($request);
        $suggestions = array_map($this->serialize(...), $this->suggestions->all($status, $branchScope));

        return new WP_REST_Response($suggestions);
    }

    private function resolveBranchScope(WP_REST_Request $request): int|false|null
    {
        if (current_user_can(WarehouseCapability::MANAGE_PURCHASE_SUGGESTIONS->value)) {
            $raw = $request->get_param('branch_id');

            if ($raw === null || $raw === '') {
                return false;
            }

            return $raw === 'hq' ? null : (int) $raw;
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    private function canAccessSuggestion(PurchaseSuggestion $suggestion): bool
    {
        if (current_user_can(WarehouseCapability::MANAGE_PURCHASE_SUGGESTIONS->value)) {
            return true;
        }

        $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        return $ownBranchId !== null && $suggestion->branchId === $ownBranchId;
    }

    public function dismiss(WP_REST_Request $request): WP_REST_Response
    {
        $suggestion = $this->suggestions->find((int) $request->get_param('id'));

        if ($suggestion === null || !$this->canAccessSuggestion($suggestion)) {
            return new WP_REST_Response(['message' => __('Öneri bulunamadı.', 'seviye-depo')], 404);
        }

        if ($suggestion->status !== PurchaseSuggestionStatus::PENDING) {
            $message = __('Yalnızca bekleyen bir öneri reddedilebilir.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $this->suggestions->dismiss($suggestion->id);

        return new WP_REST_Response($this->serialize($this->suggestions->find($suggestion->id)));
    }

    public function convert(WP_REST_Request $request): WP_REST_Response
    {
        $suggestion = $this->suggestions->find((int) $request->get_param('id'));

        if ($suggestion === null || !$this->canAccessSuggestion($suggestion)) {
            return new WP_REST_Response(['message' => __('Öneri bulunamadı.', 'seviye-depo')], 404);
        }

        if ($suggestion->status !== PurchaseSuggestionStatus::PENDING) {
            $message = __('Yalnızca bekleyen bir öneri siparişe çevrilebilir.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $supplierId = (int) $request->get_param('supplier_id');

        if ($this->suppliers->find($supplierId) === null) {
            return new WP_REST_Response(['message' => __('Geçersiz tedarikçi.', 'seviye-depo')], 422);
        }

        $quantity = $request->get_param('quantity');
        $quantity = $quantity !== null && $quantity !== '' ? (int) $quantity : $suggestion->suggestedQuantity;

        if ($quantity <= 0) {
            return new WP_REST_Response(['message' => __('Geçerli bir miktar gerekli.', 'seviye-depo')], 422);
        }

        $order = $this->purchaseOrders->create(
            $supplierId,
            null,
            $suggestion->reason,
            get_current_user_id(),
            [['product_id' => $suggestion->productId, 'quantity_ordered' => $quantity, 'unit_cost' => null]],
            $suggestion->branchId
        );

        $this->suggestions->convert($suggestion->id, $order->id);

        return new WP_REST_Response($this->serialize($this->suggestions->find($suggestion->id)));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(?PurchaseSuggestion $suggestion): array
    {
        if ($suggestion === null) {
            return [];
        }

        $branch = $suggestion->branchId !== null ? $this->branches->find($suggestion->branchId) : null;

        return [
            'id' => $suggestion->id,
            'product_id' => $suggestion->productId,
            'suggested_quantity' => $suggestion->suggestedQuantity,
            'status' => $suggestion->status->value,
            'reason' => $suggestion->reason,
            'converted_purchase_order_id' => $suggestion->convertedPurchaseOrderId,
            'created_at' => $suggestion->createdAt,
            'branch_id' => $suggestion->branchId,
            'branch_name' => $branch?->name ?? __('Genel Merkez', 'seviye-depo'),
        ];
    }
}
