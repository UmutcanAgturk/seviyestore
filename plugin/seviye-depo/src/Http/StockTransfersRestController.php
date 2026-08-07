<?php

declare(strict_types=1);

namespace Seviye\Depo\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Depo\Domain\StockMovementType;
use Seviye\Depo\Domain\StockTransfer;
use Seviye\Depo\Domain\StockTransferStatus;
use Seviye\Depo\Rbac\WarehouseCapability;
use Seviye\Depo\Repository\StockMovementRepositoryInterface;
use Seviye\Depo\Repository\StockTransferRepositoryInterface;
use WC_Product;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/depo/stock-transfers/* - "Şubeler arası stok transferi", Faz
 * 4'ün doğal devamı. Bir SKU'nun WooCommerce'te tek bir stock_quantity'si
 * olduğundan (depo başına ayrı bir stok havuzu yok - bkz.
 * docs/ARCHITECTURE.md, "Kural"), bir transfer iki FARKLI ürün kaydı
 * arasında (fromProductId'den toProductId'ye) çalışır - bkz.
 * Domain\StockTransfer'ın kendi docblock'u.
 *
 * PENDING -> COMPLETED/CANCELLED, PurchaseOrder'ın send()/receive()
 * ayrımıyla aynı gerekçe: store() hiçbir stok değiştirmez (mal henüz yola
 * çıkmamıştır), complete() (yalnızca HEDEF tarafın "teslim aldım" onayı -
 * bkz. canCompleteTransfer()) hem WC stoğunu hem defteri günceller.
 *
 * MANAGE_STOCK_TRANSFERS (platform-wide) herhangi iki depo arasında
 * transfer açabilir/tamamlayabilir/iptal edebilir. Yalnızca
 * MANAGE_OWN_BRANCH_STOCK_TRANSFERS'a sahip bir Şube Müdürü: store()'da
 * yalnızca KENDİ şubesi kaynak olacak şekilde transfer açabilir (hedef
 * herhangi bir depo olabilir - vermek her zaman serbest), complete()'te
 * yalnızca KENDİ şubesi hedef olduğunda tamamlayabilir (başka birinin
 * deposuna izinsiz stok itilmesin diye), cancel()/görüntülemede ise HER
 * İKİ taraf da "kendi" transferi sayılır - bkz. canAccessTransfer()/
 * canCompleteTransfer().
 */
final class StockTransfersRestController extends AbstractRestController
{
    public function __construct(
        private readonly StockTransferRepositoryInterface $transfers,
        private readonly StockMovementRepositoryInterface $stockMovements,
        private readonly BranchLookupInterface $branches,
        private readonly BranchMembershipInterface $branchMemberships
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/stock-transfers', [
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

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/stock-transfers/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/stock-transfers/(?P<id>\d+)/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'complete'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/depo/stock-transfers/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel'],
            'permission_callback' => [$this, 'canManageWarehouse'],
        ]);
    }

    public function canManageWarehouse(): bool
    {
        return current_user_can(WarehouseCapability::MANAGE_STOCK_TRANSFERS->value)
            || current_user_can(WarehouseCapability::MANAGE_OWN_BRANCH_STOCK_TRANSFERS->value);
    }

    /**
     * PurchaseOrdersRestController::resolveBranchScope() ile aynı üç
     * durumlu desen, tek fark: own-branch bir kullanıcı için filtre
     * "kendi şubem KAYNAK ya da HEDEF" anlamına geliyor (bkz.
     * StockTransferRepositoryInterface::all()'ın docblock'u).
     */
    private function resolveBranchScope(WP_REST_Request $request): int|false|null
    {
        if (current_user_can(WarehouseCapability::MANAGE_STOCK_TRANSFERS->value)) {
            $raw = $request->get_param('branch_id');

            if ($raw === null || $raw === '') {
                return false;
            }

            return $raw === 'hq' ? null : (int) $raw;
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    /** Görüntüleme/iptal - her iki taraf da "kendi" transferi sayılır. */
    private function canAccessTransfer(StockTransfer $transfer): bool
    {
        if (current_user_can(WarehouseCapability::MANAGE_STOCK_TRANSFERS->value)) {
            return true;
        }

        $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        return $ownBranchId !== null
            && ($transfer->fromBranchId === $ownBranchId || $transfer->toBranchId === $ownBranchId);
    }

    /** Tamamlama (mal kabul) - yalnızca HEDEF taraf onaylayabilir. */
    private function canCompleteTransfer(StockTransfer $transfer): bool
    {
        if (current_user_can(WarehouseCapability::MANAGE_STOCK_TRANSFERS->value)) {
            return true;
        }

        $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        return $ownBranchId !== null && $transfer->toBranchId === $ownBranchId;
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $status = StockTransferStatus::tryFrom((string) ($request->get_param('status') ?? ''));
        $transfers = array_map(
            $this->serialize(...),
            $this->transfers->all($status, $this->resolveBranchScope($request))
        );

        return new WP_REST_Response($transfers);
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $fromProductId = (int) $request->get_param('from_product_id');
        $toProductId = (int) $request->get_param('to_product_id');
        $quantity = (int) $request->get_param('quantity');

        if ($fromProductId <= 0 || $toProductId <= 0 || $quantity <= 0) {
            $message = __('Geçerli bir kaynak ürün, hedef ürün ve miktar gerekli.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        if ($fromProductId === $toProductId) {
            $message = __('Kaynak ve hedef ürün aynı olamaz.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $fromBranchId = $this->ownerBranchId($fromProductId);
        $toBranchId = $this->ownerBranchId($toProductId);

        if ($fromBranchId === $toBranchId) {
            $message = __('Kaynak ve hedef ürün aynı depoya ait olamaz.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        if (!current_user_can(WarehouseCapability::MANAGE_STOCK_TRANSFERS->value)) {
            $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

            if ($ownBranchId === null || $fromBranchId !== $ownBranchId) {
                $message = __(
                    'Yalnızca kendi şubenizin deposundan stok transferi başlatabilirsiniz.',
                    'seviye-depo'
                );

                return new WP_REST_Response(['message' => $message], 403);
            }
        }

        $note = trim((string) ($request->get_param('note') ?? ''));

        $transfer = $this->transfers->create(
            $fromProductId,
            $toProductId,
            $quantity,
            $fromBranchId,
            $toBranchId,
            $note !== '' ? $note : null,
            get_current_user_id()
        );

        return new WP_REST_Response($this->serialize($transfer), 201);
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $transfer = $this->transfers->find((int) $request->get_param('id'));

        if ($transfer === null || !$this->canAccessTransfer($transfer)) {
            return new WP_REST_Response(['message' => __('Stok transferi bulunamadı.', 'seviye-depo')], 404);
        }

        return new WP_REST_Response($this->serialize($transfer));
    }

    public function complete(WP_REST_Request $request): WP_REST_Response
    {
        $transfer = $this->transfers->find((int) $request->get_param('id'));

        if ($transfer === null || !$this->canAccessTransfer($transfer)) {
            return new WP_REST_Response(['message' => __('Stok transferi bulunamadı.', 'seviye-depo')], 404);
        }

        if (!$this->canCompleteTransfer($transfer)) {
            $message = __('Yalnızca hedef depo bir transferi teslim aldım olarak onaylayabilir.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 403);
        }

        if ($transfer->status !== StockTransferStatus::PENDING) {
            $message = __('Yalnızca bekleyen bir transfer tamamlanabilir.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        if (function_exists('wc_get_product')) {
            $fromProduct = wc_get_product($transfer->fromProductId);

            if (
                $fromProduct instanceof WC_Product
                && $fromProduct->get_manage_stock()
                && (int) $fromProduct->get_stock_quantity() < $transfer->quantity
            ) {
                $message = __('Kaynak depoda yeterli stok yok.', 'seviye-depo');

                return new WP_REST_Response(['message' => $message], 422);
            }
        }

        if (function_exists('wc_update_product_stock')) {
            wc_update_product_stock($transfer->fromProductId, $transfer->quantity, 'decrease');
            wc_update_product_stock($transfer->toProductId, $transfer->quantity, 'increase');
        }

        $this->stockMovements->record(
            $transfer->fromProductId,
            StockMovementType::TRANSFER_OUT,
            -$transfer->quantity,
            'stock_transfer',
            $transfer->id,
            $transfer->note,
            get_current_user_id(),
            $transfer->fromBranchId
        );

        $this->stockMovements->record(
            $transfer->toProductId,
            StockMovementType::TRANSFER_IN,
            $transfer->quantity,
            'stock_transfer',
            $transfer->id,
            $transfer->note,
            get_current_user_id(),
            $transfer->toBranchId
        );

        $this->transfers->complete($transfer->id, get_current_user_id());

        return new WP_REST_Response($this->serialize($this->transfers->find($transfer->id)));
    }

    public function cancel(WP_REST_Request $request): WP_REST_Response
    {
        $transfer = $this->transfers->find((int) $request->get_param('id'));

        if ($transfer === null || !$this->canAccessTransfer($transfer)) {
            return new WP_REST_Response(['message' => __('Stok transferi bulunamadı.', 'seviye-depo')], 404);
        }

        if ($transfer->status !== StockTransferStatus::PENDING) {
            $message = __('Yalnızca bekleyen bir transfer iptal edilebilir.', 'seviye-depo');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $this->transfers->cancel($transfer->id);

        return new WP_REST_Response($this->serialize($this->transfers->find($transfer->id)));
    }

    private function ownerBranchId(int $productId): ?int
    {
        if (!function_exists('apply_filters')) {
            return null;
        }

        $branchId = apply_filters('scp_commerce_product_owner_branch_id', null, $productId);

        return $branchId !== null ? (int) $branchId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(?StockTransfer $transfer): array
    {
        if ($transfer === null) {
            return [];
        }

        $fromBranch = $transfer->fromBranchId !== null ? $this->branches->find($transfer->fromBranchId) : null;
        $toBranch = $transfer->toBranchId !== null ? $this->branches->find($transfer->toBranchId) : null;

        return [
            'id' => $transfer->id,
            'from_product_id' => $transfer->fromProductId,
            'to_product_id' => $transfer->toProductId,
            'quantity' => $transfer->quantity,
            'from_branch_id' => $transfer->fromBranchId,
            'from_branch_name' => $fromBranch?->name ?? __('Genel Merkez', 'seviye-depo'),
            'to_branch_id' => $transfer->toBranchId,
            'to_branch_name' => $toBranch?->name ?? __('Genel Merkez', 'seviye-depo'),
            'status' => $transfer->status->value,
            'note' => $transfer->note,
            'requested_by' => $transfer->requestedByUserId,
            'completed_by' => $transfer->completedByUserId,
            'created_at' => $transfer->createdAt,
            'completed_at' => $transfer->completedAt,
        ];
    }
}
