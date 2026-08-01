<?php

declare(strict_types=1);

namespace Seviye\Depo\Http;

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
 */
final class PurchaseSuggestionsRestController extends AbstractRestController
{
    public function __construct(
        private readonly PurchaseSuggestionRepositoryInterface $suggestions,
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly SupplierRepositoryInterface $suppliers
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-suggestions', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => $this->requireCapability(WarehouseCapability::MANAGE_PURCHASE_SUGGESTIONS->value),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-suggestions/(?P<id>\d+)/dismiss', [
            'methods' => 'POST',
            'callback' => [$this, 'dismiss'],
            'permission_callback' => $this->requireCapability(WarehouseCapability::MANAGE_PURCHASE_SUGGESTIONS->value),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-suggestions/(?P<id>\d+)/convert', [
            'methods' => 'POST',
            'callback' => [$this, 'convert'],
            'permission_callback' => $this->requireCapability(WarehouseCapability::MANAGE_PURCHASE_SUGGESTIONS->value),
        ]);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $status = PurchaseSuggestionStatus::tryFrom((string) ($request->get_param('status') ?? ''));
        $suggestions = array_map($this->serialize(...), $this->suggestions->all($status));

        return new WP_REST_Response($suggestions);
    }

    public function dismiss(WP_REST_Request $request): WP_REST_Response
    {
        $suggestion = $this->suggestions->find((int) $request->get_param('id'));

        if ($suggestion === null) {
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

        if ($suggestion === null) {
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
            [['product_id' => $suggestion->productId, 'quantity_ordered' => $quantity, 'unit_cost' => null]]
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

        return [
            'id' => $suggestion->id,
            'product_id' => $suggestion->productId,
            'suggested_quantity' => $suggestion->suggestedQuantity,
            'status' => $suggestion->status->value,
            'reason' => $suggestion->reason,
            'converted_purchase_order_id' => $suggestion->convertedPurchaseOrderId,
            'created_at' => $suggestion->createdAt,
        ];
    }
}
