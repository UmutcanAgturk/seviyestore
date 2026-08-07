<?php

declare(strict_types=1);

namespace Seviye\Depo\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Depo\Domain\PurchaseOrder;
use Seviye\Depo\Domain\PurchaseOrderItem;
use Seviye\Depo\Domain\PurchaseOrderStatus;
use Seviye\Depo\Domain\StockMovementType;
use Seviye\Depo\Rbac\WarehouseCapability;
use Seviye\Depo\Repository\PurchaseOrderRepositoryInterface;
use Seviye\Depo\Repository\StockMovementRepositoryInterface;
use Seviye\Depo\Repository\SupplierRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/depo/purchase-orders/* - satın alma siparişi CRUD + mal kabul.
 * Kalemler yalnızca oluşturma sırasında girilir (draft'tan sonra kalem
 * listesi kilitlenir - bkz. plan dokümanının "İş akışları" bölümü); mal
 * kabul yalnızca miktar günceller, kalem eklemez/çıkarmaz.
 *
 * receive()'ın stok tarafı: her kalem için WooCommerce'in kendi
 * wc_update_product_stock() fonksiyonu çağrılır (stok WC'nin tek doğruluk
 * kaynağı olmaya devam eder - bkz. docs/ARCHITECTURE.md, "Kural") ve AYRICA
 * StockMovementRepositoryInterface'e bir defter satırı yazılır - biri
 * gerçek stok sayısını, diğeri NEDEN değiştiğinin geçmişini tutar.
 *
 * Faz 4 ("bir tane genel merkezin deposu, şube ürün eklemişse kendi
 * deposu"): store()'da her kalemin sahip şubesi (Commerce'in
 * scp_commerce_product_owner_branch_id filter köprüsü) çözümlenip TÜM
 * kalemlerin AYNI depoya (hepsi Genel Merkez ya da hepsi TEK bir şube) ait
 * olduğu doğrulanıyor - fiziksel olarak bir sipariş tek bir depoya teslim
 * alınır, iki depo arasında bölünemez. MANAGE_PURCHASE_ORDERS (platform-wide)
 * her depoyu görür/yönetir; MANAGE_OWN_BRANCH_PURCHASE_ORDERS yalnızca
 * kendi şubesinin deposunu - bkz. resolveBranchScope()/canAccessOrder().
 */
final class PurchaseOrdersRestController extends AbstractRestController
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $purchaseOrders,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly StockMovementRepositoryInterface $stockMovements,
        private readonly BranchLookupInterface $branches,
        private readonly BranchMembershipInterface $branchMemberships
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-orders', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'canManageWarehouse'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => [$this, 'canManageWarehouse'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-orders/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-orders/(?P<id>\d+)/send', [
            'methods' => 'POST',
            'callback' => [$this, 'send'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-orders/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-orders/(?P<id>\d+)/receive', [
            'methods' => 'POST',
            'callback' => [$this, 'receive'],
            'permission_callback' => [$this, 'canReceiveStock'],
        ]);

        // "Tedarikçi portalı" - WarehouseCapability'den TAMAMEN bağımsız:
        // erişim, isteği yapan WP kullanıcısının scp_suppliers.user_id'ye
        // bağlı olup olmadığına göre kararlaştırılır (bkz. Role enum'ın
        // kapalı küme olduğuna dair not - Depo/DepoModule::boot()).
        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-orders/mine', [
            'methods' => 'GET',
            'callback' => [$this, 'mine'],
            'permission_callback' => $this->requireLinkedSupplier(...),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/purchase-orders/(?P<id>\d+)/mark-shipped', [
            'methods' => 'POST',
            'callback' => [$this, 'markShipped'],
            'permission_callback' => $this->requireLinkedSupplier(...),
        ]);
    }

    private function requireLinkedSupplier(): bool
    {
        return $this->suppliers->findByUserId(get_current_user_id()) !== null;
    }

    public function canManageWarehouse(): bool
    {
        return current_user_can(WarehouseCapability::MANAGE_PURCHASE_ORDERS->value)
            || current_user_can(WarehouseCapability::MANAGE_OWN_BRANCH_PURCHASE_ORDERS->value);
    }

    public function canReceiveStock(): bool
    {
        return current_user_can(WarehouseCapability::RECEIVE_STOCK->value)
            || current_user_can(WarehouseCapability::MANAGE_OWN_BRANCH_PURCHASE_ORDERS->value);
    }

    /**
     * MANAGE_PURCHASE_ORDERS (Genel Merkez/Bölge Müdürü/Depo) her depoyu
     * görür; `branch_id` sorgu parametresiyle isteğe bağlı daraltabilir:
     * yoksa/boşsa TÜM depolar, `hq` yalnızca Genel Merkez, bir sayı o
     * şubenin id'si. MANAGE_OWN_BRANCH_PURCHASE_ORDERS'a (yalnızca)
     * sahip bir Şube Müdürü için bu parametre YOK SAYILIR, her zaman
     * kendi şubesine zorlanır - bir şube başka bir şubenin (veya Genel
     * Merkez'in) deposunu asla göremez.
     */
    private function resolveBranchScope(WP_REST_Request $request): int|false|null
    {
        if (current_user_can(WarehouseCapability::MANAGE_PURCHASE_ORDERS->value)) {
            $raw = $request->get_param('branch_id');

            if ($raw === null || $raw === '') {
                return false;
            }

            return $raw === 'hq' ? null : (int) $raw;
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    private function canAccessOrder(PurchaseOrder $order): bool
    {
        if (current_user_can(WarehouseCapability::MANAGE_PURCHASE_ORDERS->value)) {
            return true;
        }

        $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        return $ownBranchId !== null && $order->branchId === $ownBranchId;
    }

    public function mine(): WP_REST_Response
    {
        $supplier = $this->suppliers->findByUserId(get_current_user_id());

        if ($supplier === null) {
            $message = __('Bu hesaba bağlı bir tedarikçi bulunamadı.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 403);
        }

        $orders = array_map($this->serialize(...), $this->purchaseOrders->all(null, $supplier->id));

        return new WP_REST_Response($orders);
    }

    public function markShipped(WP_REST_Request $request): WP_REST_Response
    {
        $supplier = $this->suppliers->findByUserId(get_current_user_id());
        $order = $this->purchaseOrders->find((int) $request->get_param('id'));

        if ($order === null || $supplier === null || $order->supplierId !== $supplier->id) {
            return new WP_REST_Response(['message' => __('Satın alma siparişi bulunamadı.', 'seviye-depo')], 404);
        }

        if (!in_array($order->status, [PurchaseOrderStatus::SENT, PurchaseOrderStatus::PARTIALLY_RECEIVED], true)) {
            $message = __('Yalnızca gönderilmiş bir sipariş için kargo bilgisi işaretlenebilir.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $this->purchaseOrders->markShipped($order->id);

        return new WP_REST_Response($this->serialize($this->purchaseOrders->find($order->id)));
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $status = PurchaseOrderStatus::tryFrom((string) ($request->get_param('status') ?? ''));
        $supplierId = $request->get_param('supplier_id');
        $supplierId = $supplierId !== null && $supplierId !== '' ? (int) $supplierId : null;

        $orders = array_map(
            $this->serialize(...),
            $this->purchaseOrders->all($status, $supplierId, $this->resolveBranchScope($request))
        );

        return new WP_REST_Response($orders);
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $supplierId = (int) $request->get_param('supplier_id');

        if ($this->suppliers->find($supplierId) === null) {
            return new WP_REST_Response(['message' => __('Geçersiz tedarikçi.', 'seviye-depo')], 422);
        }

        $items = $this->normalizeItems($request->get_param('items'));

        if ($items === []) {
            return new WP_REST_Response(['message' => __('En az bir kalem gerekli.', 'seviye-depo')], 422);
        }

        // "Bir satın alma siparişi asla iki depo arasında karışık olamaz" -
        // her kalemin sahip şubesi (Genel Merkez için null) çözümlenip
        // hepsinin AYNI değere sahip olduğu doğrulanıyor.
        $orderBranchId = null;
        $firstItem = true;
        $mixedBranches = false;

        foreach ($items as $item) {
            $itemBranchId = apply_filters('scp_commerce_product_owner_branch_id', null, $item['product_id']);
            $itemBranchId = $itemBranchId !== null ? (int) $itemBranchId : null;

            if ($firstItem) {
                $orderBranchId = $itemBranchId;
                $firstItem = false;

                continue;
            }

            if ($itemBranchId !== $orderBranchId) {
                $mixedBranches = true;

                break;
            }
        }

        if ($mixedBranches) {
            $message = __(
                'Bir satın alma siparişindeki tüm ürünler aynı depoya (Genel Merkez veya tek bir şube) ait olmalı.',
                'seviye-depo'
            );

            return new WP_REST_Response(['message' => $message], 422);
        }

        if (!current_user_can(WarehouseCapability::MANAGE_PURCHASE_ORDERS->value)) {
            $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

            if ($ownBranchId === null || $orderBranchId !== $ownBranchId) {
                $message = __(
                    'Yalnızca kendi şubenizin eklediği ürünler için satın alma siparişi açabilirsiniz.',
                    'seviye-depo'
                );

                return new WP_REST_Response(['message' => $message], 403);
            }
        }

        $expectedDate = trim((string) ($request->get_param('expected_date') ?? ''));
        $note = trim((string) ($request->get_param('note') ?? ''));

        $order = $this->purchaseOrders->create(
            $supplierId,
            $expectedDate !== '' ? $expectedDate : null,
            $note !== '' ? $note : null,
            get_current_user_id(),
            $items,
            $orderBranchId
        );

        return new WP_REST_Response($this->serialize($order), 201);
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $order = $this->purchaseOrders->find((int) $request->get_param('id'));

        if ($order === null || !$this->canAccessOrder($order)) {
            return new WP_REST_Response(['message' => __('Satın alma siparişi bulunamadı.', 'seviye-depo')], 404);
        }

        return new WP_REST_Response($this->serialize($order));
    }

    public function send(WP_REST_Request $request): WP_REST_Response
    {
        $order = $this->purchaseOrders->find((int) $request->get_param('id'));

        if ($order === null || !$this->canAccessOrder($order)) {
            return new WP_REST_Response(['message' => __('Satın alma siparişi bulunamadı.', 'seviye-depo')], 404);
        }

        if ($order->status !== PurchaseOrderStatus::DRAFT) {
            $message = __('Yalnızca taslak siparişler gönderilebilir.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $this->purchaseOrders->send($order->id);

        return new WP_REST_Response($this->serialize($this->purchaseOrders->find($order->id)));
    }

    public function cancel(WP_REST_Request $request): WP_REST_Response
    {
        $order = $this->purchaseOrders->find((int) $request->get_param('id'));

        if ($order === null || !$this->canAccessOrder($order)) {
            return new WP_REST_Response(['message' => __('Satın alma siparişi bulunamadı.', 'seviye-depo')], 404);
        }

        if (in_array($order->status, [PurchaseOrderStatus::COMPLETED, PurchaseOrderStatus::CANCELLED], true)) {
            $message = __('Tamamlanmış veya zaten iptal edilmiş bir sipariş iptal edilemez.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $this->purchaseOrders->cancel($order->id);

        return new WP_REST_Response($this->serialize($this->purchaseOrders->find($order->id)));
    }

    public function receive(WP_REST_Request $request): WP_REST_Response
    {
        $order = $this->purchaseOrders->find((int) $request->get_param('id'));

        if ($order === null || !$this->canAccessOrder($order)) {
            return new WP_REST_Response(['message' => __('Satın alma siparişi bulunamadı.', 'seviye-depo')], 404);
        }

        if (!in_array($order->status, [PurchaseOrderStatus::SENT, PurchaseOrderStatus::PARTIALLY_RECEIVED], true)) {
            $message = __('Yalnızca gönderilmiş bir sipariş için mal kabul yapılabilir.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        // Yalnızca bu siparişin GERÇEK kalemlerine yazılır - ProductsRestController::updateVariations()'daki
        // aynı güvenlik önlemi: rastgele/başka bir siparişe ait bir item_id verilmesini engeller.
        $validItemIds = array_map(static fn (PurchaseOrderItem $item): int => $item->id, $order->items);
        $receipts = $this->normalizeReceipts($request->get_param('items'), $validItemIds);

        if ($receipts === []) {
            return new WP_REST_Response(['message' => __('Geçerli bir teslimat kalemi gerekli.', 'seviye-depo')], 422);
        }

        foreach ($receipts as $itemId => $quantity) {
            $item = $this->purchaseOrders->receiveItem($itemId, $quantity);

            if (function_exists('wc_update_product_stock')) {
                wc_update_product_stock($item->productId, $quantity, 'increase');
            }

            $this->stockMovements->record(
                $item->productId,
                StockMovementType::PURCHASE_IN,
                $quantity,
                'purchase_order',
                $order->id,
                sprintf('Mal kabul: %s', $order->code),
                get_current_user_id(),
                $order->branchId
            );
        }

        return new WP_REST_Response($this->serialize($this->purchaseOrders->find($order->id)));
    }

    /**
     * @param mixed $raw
     * @return list<array{product_id: int, quantity_ordered: int, unit_cost: ?float}>
     */
    private function normalizeItems(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $items = [];

        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $productId = (int) ($entry['product_id'] ?? 0);
            $quantity = (int) ($entry['quantity_ordered'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $unitCost = isset($entry['unit_cost']) && $entry['unit_cost'] !== '' ? (float) $entry['unit_cost'] : null;

            $items[] = ['product_id' => $productId, 'quantity_ordered' => $quantity, 'unit_cost' => $unitCost];
        }

        return $items;
    }

    /**
     * @param mixed $raw
     * @param list<int> $validItemIds
     * @return array<int, int> quantity keyed by item id
     */
    private function normalizeReceipts(mixed $raw, array $validItemIds): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $receipts = [];

        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $itemId = (int) ($entry['item_id'] ?? 0);
            $quantity = (int) ($entry['quantity_received'] ?? 0);

            if ($quantity <= 0 || !in_array($itemId, $validItemIds, true)) {
                continue;
            }

            $receipts[$itemId] = $quantity;
        }

        return $receipts;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PurchaseOrder $order): array
    {
        $branch = $order->branchId !== null ? $this->branches->find($order->branchId) : null;

        return [
            'id' => $order->id,
            'supplier_id' => $order->supplierId,
            'code' => $order->code,
            'status' => $order->status->value,
            'expected_date' => $order->expectedDate,
            'note' => $order->note,
            'created_by' => $order->createdByUserId,
            'created_at' => $order->createdAt,
            'supplier_shipped_at' => $order->supplierShippedAt,
            'branch_id' => $order->branchId,
            'branch_name' => $branch?->name ?? __('Genel Merkez', 'seviye-depo'),
            'items' => array_map($this->serializeItem(...), $order->items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(PurchaseOrderItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->productId,
            'quantity_ordered' => $item->quantityOrdered,
            'quantity_received' => $item->quantityReceived,
            'remaining_quantity' => $item->remainingQuantity(),
            'unit_cost' => $item->unitCost,
        ];
    }
}
