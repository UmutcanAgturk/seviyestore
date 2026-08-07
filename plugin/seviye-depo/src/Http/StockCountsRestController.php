<?php

declare(strict_types=1);

namespace Seviye\Depo\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Depo\Domain\StockCount;
use Seviye\Depo\Domain\StockCountItem;
use Seviye\Depo\Domain\StockCountStatus;
use Seviye\Depo\Domain\StockMovementType;
use Seviye\Depo\Rbac\WarehouseCapability;
use Seviye\Depo\Repository\StockCountRepositoryInterface;
use Seviye\Depo\Repository\StockMovementRepositoryInterface;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/depo/stock-counts/* - stok sayımı (cycle count, faz 2). store()
 * WC'nin manage_stock açık her ürün/varyasyonunu tarayıp o anki stok
 * miktarını expected_quantity olarak dondurur (bkz. plan dokümanının
 * "İş akışları" bölümü, adım 1) - bu tarama WC'ye bağımlı olduğundan
 * repository'de değil burada yapılır, PurchaseOrdersRestController::receive()'ın
 * wc_update_product_stock()'u Http katmanında çağırmasıyla aynı ilke.
 * complete() farkı sıfır olmayan her kalem için aynı iki şeyi yapar:
 * wc_update_product_stock() ile gerçek stoğu düzeltir VE
 * StockMovementRepositoryInterface'e type=count_adjustment satırı yazar.
 *
 * Faz 4: store() artık taradığı ürünleri sayımın kapsadığı depoya
 * (Genel Merkez ya da tek bir şube) göre filtreliyor - bkz.
 * currentManagedStockLevels()'ın kendi docblock'u.
 * MANAGE_STOCK_COUNTS (platform-wide) hangi depoyu sayacağını `branch_id`
 * body parametresiyle seçer; MANAGE_OWN_BRANCH_STOCK_COUNTS'a sahip bir
 * Şube Müdürü için bu parametre YOK SAYILIR, her zaman kendi şubesine
 * zorlanır.
 */
final class StockCountsRestController extends AbstractRestController
{
    public function __construct(
        private readonly StockCountRepositoryInterface $stockCounts,
        private readonly StockMovementRepositoryInterface $stockMovements,
        private readonly BranchLookupInterface $branches,
        private readonly BranchMembershipInterface $branchMemberships
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/stock-counts', [
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

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/stock-counts/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/stock-counts/(?P<id>\d+)/items/(?P<item_id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateItem'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/stock-counts/(?P<id>\d+)/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'complete'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);
    }

    public function canManageWarehouse(): bool
    {
        return current_user_can(WarehouseCapability::MANAGE_STOCK_COUNTS->value)
            || current_user_can(WarehouseCapability::MANAGE_OWN_BRANCH_STOCK_COUNTS->value);
    }

    /**
     * PurchaseOrdersRestController::resolveBranchScope() ile AYNI desen -
     * bkz. o metodun docblock'u.
     */
    private function resolveBranchScope(WP_REST_Request $request): int|false|null
    {
        if (current_user_can(WarehouseCapability::MANAGE_STOCK_COUNTS->value)) {
            $raw = $request->get_param('branch_id');

            if ($raw === null || $raw === '') {
                return false;
            }

            return $raw === 'hq' ? null : (int) $raw;
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    private function canAccessStockCount(StockCount $stockCount): bool
    {
        if (current_user_can(WarehouseCapability::MANAGE_STOCK_COUNTS->value)) {
            return true;
        }

        $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        return $ownBranchId !== null && $stockCount->branchId === $ownBranchId;
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $status = StockCountStatus::tryFrom((string) ($request->get_param('status') ?? ''));
        $counts = array_map(
            $this->serialize(...),
            $this->stockCounts->all($status, $this->resolveBranchScope($request))
        );

        return new WP_REST_Response($counts);
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        // Platform-wide bir kullanıcı için: `branch_id` body parametresi
        // yoksa/boşsa Genel Merkez deposu sayılır (varsayılan hâlâ Genel
        // Merkez - bu Faz 4'ten önceki tek davranışla geriye dönük
        // tutarlılık). Own-branch kullanıcı için her zaman kendi şubesi.
        if (current_user_can(WarehouseCapability::MANAGE_STOCK_COUNTS->value)) {
            $raw = $request->get_param('branch_id');
            $branchId = $raw === null || $raw === '' || $raw === 'hq' ? null : (int) $raw;
        } else {
            $branchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

            if ($branchId === null) {
                return new WP_REST_Response(['message' => __('Şubeniz bulunamadı.', 'seviye-depo')], 403);
            }
        }

        $levels = $this->currentManagedStockLevels($branchId);

        if ($levels === []) {
            $message = __('Bu depoda stok takibi açık hiçbir ürün bulunamadı.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $stockCount = $this->stockCounts->open($levels, get_current_user_id(), $branchId);

        return new WP_REST_Response($this->serialize($stockCount), 201);
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $stockCount = $this->stockCounts->find((int) $request->get_param('id'));

        if ($stockCount === null || !$this->canAccessStockCount($stockCount)) {
            return new WP_REST_Response(['message' => __('Stok sayımı bulunamadı.', 'seviye-depo')], 404);
        }

        return new WP_REST_Response($this->serialize($stockCount));
    }

    public function updateItem(WP_REST_Request $request): WP_REST_Response
    {
        $stockCount = $this->stockCounts->find((int) $request->get_param('id'));

        if ($stockCount === null || !$this->canAccessStockCount($stockCount)) {
            return new WP_REST_Response(['message' => __('Stok sayımı bulunamadı.', 'seviye-depo')], 404);
        }

        if ($stockCount->status !== StockCountStatus::OPEN) {
            $message = __('Yalnızca açık bir sayımın kalemleri güncellenebilir.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $itemId = (int) $request->get_param('item_id');
        $validItemIds = array_map(static fn (StockCountItem $item): int => $item->id, $stockCount->items);

        // ProductsRestController::updateVariations()'daki aynı güvenlik önlemi:
        // rastgele/başka bir sayıma ait bir item_id verilerek onun kalemi
        // manipüle edilmesini engelliyor.
        if (!in_array($itemId, $validItemIds, true)) {
            return new WP_REST_Response(['message' => __('Sayım kalemi bulunamadı.', 'seviye-depo')], 404);
        }

        $countedQuantity = $request->get_param('counted_quantity');

        if ($countedQuantity === null || $countedQuantity === '' || (int) $countedQuantity < 0) {
            return new WP_REST_Response(['message' => __('Geçerli bir sayılan miktar gerekli.', 'seviye-depo')], 422);
        }

        $item = $this->stockCounts->setCountedQuantity($itemId, (int) $countedQuantity);

        return new WP_REST_Response($this->serializeItem($item));
    }

    public function complete(WP_REST_Request $request): WP_REST_Response
    {
        $stockCount = $this->stockCounts->find((int) $request->get_param('id'));

        if ($stockCount === null || !$this->canAccessStockCount($stockCount)) {
            return new WP_REST_Response(['message' => __('Stok sayımı bulunamadı.', 'seviye-depo')], 404);
        }

        if ($stockCount->status !== StockCountStatus::OPEN) {
            return new WP_REST_Response(['message' => __('Bu sayım zaten tamamlanmış.', 'seviye-depo')], 422);
        }

        foreach ($stockCount->items as $item) {
            $variance = $item->variance();

            if ($variance === null || $variance === 0) {
                continue;
            }

            if (function_exists('wc_update_product_stock')) {
                wc_update_product_stock($item->productId, abs($variance), $variance > 0 ? 'increase' : 'decrease');
            }

            $this->stockMovements->record(
                $item->productId,
                StockMovementType::COUNT_ADJUSTMENT,
                $variance,
                'stock_count',
                $stockCount->id,
                sprintf('Stok sayımı #%d düzeltmesi', $stockCount->id),
                get_current_user_id(),
                $stockCount->branchId
            );
        }

        $completed = $this->stockCounts->complete($stockCount->id, get_current_user_id());

        return new WP_REST_Response($this->serialize($completed));
    }

    /**
     * WC'de manage_stock açık her basit ürünü VE her varyasyonu tarar -
     * ProductsRestController::index()'in wc_get_products() çağrısıyla aynı
     * filtre (publish/draft), ancak burada yalnızca gerçekten stok takibi
     * yapılan VE $branchId kapsamına ait (Commerce'in
     * scp_commerce_product_owner_branch_id filter köprüsüyle çözümlenen)
     * kalemler tutuluyor - $branchId=null Genel Merkez'in kendi ürünleri,
     * bir int ise yalnızca o şubenin eklediği ürünler.
     *
     * @return array<int, int> productId => stok miktarı
     */
    private function currentManagedStockLevels(?int $branchId): array
    {
        if (!function_exists('wc_get_products')) {
            return [];
        }

        $levels = [];
        $products = wc_get_products(['limit' => -1, 'status' => ['publish', 'draft']]);

        foreach ($products as $product) {
            if (!$product instanceof WC_Product) {
                continue;
            }

            $ownerBranchId = apply_filters('scp_commerce_product_owner_branch_id', null, $product->get_id());
            $ownerBranchId = $ownerBranchId !== null ? (int) $ownerBranchId : null;

            if ($ownerBranchId !== $branchId) {
                continue;
            }

            if ($product instanceof WC_Product_Variable) {
                foreach ($product->get_children() as $variationId) {
                    $variation = wc_get_product($variationId);

                    if ($variation instanceof WC_Product_Variation && $variation->get_manage_stock()) {
                        $levels[$variation->get_id()] = (int) $variation->get_stock_quantity();
                    }
                }

                continue;
            }

            if ($product->get_manage_stock()) {
                $levels[$product->get_id()] = (int) $product->get_stock_quantity();
            }
        }

        return $levels;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(StockCount $stockCount): array
    {
        $branch = $stockCount->branchId !== null ? $this->branches->find($stockCount->branchId) : null;

        return [
            'id' => $stockCount->id,
            'status' => $stockCount->status->value,
            'started_by' => $stockCount->startedByUserId,
            'completed_by' => $stockCount->completedByUserId,
            'started_at' => $stockCount->startedAt,
            'completed_at' => $stockCount->completedAt,
            'branch_id' => $stockCount->branchId,
            'branch_name' => $branch?->name ?? __('Genel Merkez', 'seviye-depo'),
            'items' => array_map($this->serializeItem(...), $stockCount->items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(StockCountItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->productId,
            'expected_quantity' => $item->expectedQuantity,
            'counted_quantity' => $item->countedQuantity,
            'variance' => $item->variance(),
        ];
    }
}
