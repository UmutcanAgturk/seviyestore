<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\SubeSiparis\Domain\BranchOrder;
use Seviye\SubeSiparis\Domain\BranchOrderItem;
use Seviye\SubeSiparis\Domain\BranchOrderStatus;
use Seviye\SubeSiparis\Rbac\BranchOrderCapability;
use Seviye\SubeSiparis\Repository\BranchOrderQuotaRepositoryInterface;
use Seviye\SubeSiparis\Repository\BranchOrderRepositoryInterface;
use Seviye\SubeSiparis\Support\BranchOrderSplitCalculator;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/sube-siparis/orders/* - şube siparişi CRUD (draft aşamasında)
 * + gönderme + Genel Merkez onay/red + WooCommerce ödeme linki.
 *
 * "Her bir şube için ayrı ayrı ürün girişi yapılabilsin" - store()/update()
 * yalnızca MANAGE_OWN_BRANCH_ORDERS sahibinin KENDİ şubesine (ya da
 * MANAGE_BRANCH_ORDERS sahibinin branch_id ile belirttiği herhangi bir
 * şubeye) yazar. approve()/reject() yalnızca MANAGE_BRANCH_ORDERS'ta -
 * bir şube kendi siparişini asla onaylayamaz.
 *
 * approve()'un ödeme tarafı: aşan (paid) miktar varsa
 * {@see WooCommercePaymentBridge} gerçek bir WooCommerce siparişi açar,
 * dönen ödeme linkini response'a `payment_url` olarak ekler - şube müdürü
 * o linkten WooCommerce'in kendi (kart dahil) ödeme yöntemleriyle öder.
 */
final class BranchOrdersRestController extends AbstractRestController
{
    public function __construct(
        private readonly BranchOrderRepositoryInterface $branchOrders,
        private readonly BranchOrderQuotaRepositoryInterface $quotas,
        private readonly BranchOrderSplitCalculator $splitCalculator,
        private readonly WooCommercePaymentBridge $paymentBridge,
        private readonly BranchLookupInterface $branches,
        private readonly BranchMembershipInterface $branchMemberships
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/sube-siparis/orders', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'canAccessBranchOrders'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => [$this, 'canAccessBranchOrders'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/sube-siparis/orders/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'canAccessBranchOrders'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'canAccessBranchOrders'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/sube-siparis/orders/(?P<id>\d+)/submit', [
            'methods' => 'POST',
            'callback' => [$this, 'submit'],
            'permission_callback' => [$this, 'canAccessBranchOrders'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/sube-siparis/orders/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel'],
            'permission_callback' => [$this, 'canAccessBranchOrders'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/sube-siparis/orders/(?P<id>\d+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve'],
            'permission_callback' => $this->requireCapability(BranchOrderCapability::MANAGE_BRANCH_ORDERS->value),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/sube-siparis/orders/(?P<id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject'],
            'permission_callback' => $this->requireCapability(BranchOrderCapability::MANAGE_BRANCH_ORDERS->value),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/sube-siparis/orders/(?P<id>\d+)/payment-url', [
            'methods' => 'GET',
            'callback' => [$this, 'paymentUrl'],
            'permission_callback' => [$this, 'canAccessBranchOrders'],
        ]);
    }

    public function canAccessBranchOrders(): bool
    {
        return current_user_can(BranchOrderCapability::MANAGE_BRANCH_ORDERS->value)
            || current_user_can(BranchOrderCapability::MANAGE_OWN_BRANCH_ORDERS->value);
    }

    /**
     * MANAGE_BRANCH_ORDERS her şubeyi görür; `branch_id` sorgu
     * parametresiyle isteğe bağlı daraltabilir. MANAGE_OWN_BRANCH_ORDERS'a
     * (yalnızca) sahip bir Şube Müdürü için bu parametre YOK SAYILIR, her
     * zaman kendi şubesine zorlanır - branchIdForUser() null dönerse
     * (üyeliği yoksa) repository'nin `branch_id IS NULL` filtresi hiçbir
     * satırla eşleşmez (branch_id NOT NULL) - güvenli, boş sonuç.
     */
    private function resolveBranchScope(WP_REST_Request $request): int|false|null
    {
        if (current_user_can(BranchOrderCapability::MANAGE_BRANCH_ORDERS->value)) {
            $raw = $request->get_param('branch_id');

            if ($raw === null || $raw === '') {
                return false;
            }

            return (int) $raw;
        }

        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    private function canAccessOrder(BranchOrder $order): bool
    {
        if (current_user_can(BranchOrderCapability::MANAGE_BRANCH_ORDERS->value)) {
            return true;
        }

        $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        return $ownBranchId !== null && $order->branchId === $ownBranchId;
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $status = BranchOrderStatus::tryFrom((string) ($request->get_param('status') ?? ''));

        $orders = array_map(
            $this->serialize(...),
            $this->branchOrders->all($status, $this->resolveBranchScope($request))
        );

        return new WP_REST_Response($orders);
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        if (current_user_can(BranchOrderCapability::MANAGE_BRANCH_ORDERS->value)) {
            $branchId = (int) $request->get_param('branch_id');

            if ($branchId <= 0 || !$this->branches->exists($branchId)) {
                return new WP_REST_Response(['message' => __('Geçersiz şube.', 'seviye-sube-siparis')], 422);
            }
        } else {
            $branchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

            if ($branchId === null) {
                $message = __('Hesabınız herhangi bir şubeye bağlı değil.', 'seviye-sube-siparis');

                return new WP_REST_Response(['message' => $message], 403);
            }
        }

        $items = $this->normalizeItems($request->get_param('items'));

        if ($items === []) {
            return new WP_REST_Response(['message' => __('En az bir kalem gerekli.', 'seviye-sube-siparis')], 422);
        }

        $note = trim((string) ($request->get_param('note') ?? ''));

        $order = $this->branchOrders->create($branchId, get_current_user_id(), $note !== '' ? $note : null, $items);

        return new WP_REST_Response($this->serialize($order), 201);
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $order = $this->branchOrders->find((int) $request->get_param('id'));

        if ($order === null || !$this->canAccessOrder($order)) {
            return $this->notFound();
        }

        return new WP_REST_Response($this->serialize($order));
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $order = $this->branchOrders->find((int) $request->get_param('id'));

        if ($order === null || !$this->canAccessOrder($order)) {
            return $this->notFound();
        }

        if ($order->status !== BranchOrderStatus::DRAFT) {
            $message = __('Yalnızca taslak siparişlerin kalemleri düzenlenebilir.', 'seviye-sube-siparis');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $items = $this->normalizeItems($request->get_param('items'));

        if ($items === []) {
            return new WP_REST_Response(['message' => __('En az bir kalem gerekli.', 'seviye-sube-siparis')], 422);
        }

        $order = $this->branchOrders->replaceItems($order->id, $items);

        return new WP_REST_Response($this->serialize($order));
    }

    public function submit(WP_REST_Request $request): WP_REST_Response
    {
        $order = $this->branchOrders->find((int) $request->get_param('id'));

        if ($order === null || !$this->canAccessOrder($order)) {
            return $this->notFound();
        }

        if ($order->status !== BranchOrderStatus::DRAFT) {
            $message = __('Yalnızca taslak siparişler Genel Merkez onayına gönderilebilir.', 'seviye-sube-siparis');

            return new WP_REST_Response(['message' => $message], 422);
        }

        if ($order->items === []) {
            return new WP_REST_Response(['message' => __('En az bir kalem gerekli.', 'seviye-sube-siparis')], 422);
        }

        $this->branchOrders->submit($order->id);

        return new WP_REST_Response($this->serialize($this->branchOrders->find($order->id)));
    }

    public function cancel(WP_REST_Request $request): WP_REST_Response
    {
        $order = $this->branchOrders->find((int) $request->get_param('id'));

        if ($order === null || !$this->canAccessOrder($order)) {
            return $this->notFound();
        }

        if (!in_array($order->status, [BranchOrderStatus::DRAFT, BranchOrderStatus::SUBMITTED], true)) {
            $message = __(
                'Yalnızca taslak veya onay bekleyen bir sipariş vazgeçilebilir.',
                'seviye-sube-siparis'
            );

            return new WP_REST_Response(['message' => $message], 422);
        }

        $this->branchOrders->cancel($order->id);

        return new WP_REST_Response($this->serialize($this->branchOrders->find($order->id)));
    }

    public function reject(WP_REST_Request $request): WP_REST_Response
    {
        $order = $this->branchOrders->find((int) $request->get_param('id'));

        if ($order === null) {
            return $this->notFound();
        }

        if ($order->status !== BranchOrderStatus::SUBMITTED) {
            $message = __('Yalnızca onay bekleyen bir sipariş reddedilebilir.', 'seviye-sube-siparis');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $reason = trim((string) ($request->get_param('reason') ?? ''));

        if ($reason === '') {
            return new WP_REST_Response(['message' => __('Red gerekçesi gerekli.', 'seviye-sube-siparis')], 422);
        }

        $this->branchOrders->reject($order->id, $reason);

        return new WP_REST_Response($this->serialize($this->branchOrders->find($order->id)));
    }

    /**
     * Her kalem için o anki tüketime göre ücretsiz/ücretli ayrımını
     * hesaplar (bkz. Support\BranchOrderSplitCalculator), ücretli kalem
     * varsa gerçek bir WooCommerce siparişi açıp ödeme linkini
     * `payment_url` olarak döner - bkz. bu sınıfın kendi docblock'u.
     */
    public function approve(WP_REST_Request $request): WP_REST_Response
    {
        $order = $this->branchOrders->find((int) $request->get_param('id'));

        if ($order === null) {
            return $this->notFound();
        }

        if ($order->status !== BranchOrderStatus::SUBMITTED) {
            $message = __('Yalnızca onay bekleyen bir sipariş onaylanabilir.', 'seviye-sube-siparis');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $splitsByItemId = [];

        foreach ($order->items as $item) {
            $product = function_exists('wc_get_product') ? wc_get_product($item->productId) : false;

            if ($product === false || $product === null) {
                $message = sprintf(
                    /* translators: %d: WooCommerce product id */
                    __('Ürün #%d artık bulunamıyor, sipariş onaylanamadı.', 'seviye-sube-siparis'),
                    $item->productId
                );

                return new WP_REST_Response(['message' => $message], 422);
            }

            $quota = $this->quotas->find($order->branchId, $item->productId);
            $freeQuota = $quota?->freeQuantity ?? 0;
            $alreadyConsumed = $this->branchOrders->consumedFreeQuantity($order->branchId, $item->productId);

            $split = $this->splitCalculator->split($item->quantityRequested, $freeQuota, $alreadyConsumed);

            $splitsByItemId[$item->id] = [
                'free' => $split['free'],
                'paid' => $split['paid'],
                'unit_price' => $split['paid'] > 0 ? (float) $product->get_price() : null,
            ];
        }

        $order = $this->branchOrders->approve($order->id, get_current_user_id(), $splitsByItemId);

        $paymentUrl = null;

        if ($order->hasPaidPortion()) {
            $paymentUrl = $this->paymentBridge->createOrderForBranchOrder($order);
            $order = $this->branchOrders->find($order->id) ?? $order;
        }

        $payload = $this->serialize($order);
        $payload['payment_url'] = $paymentUrl;

        return new WP_REST_Response($payload);
    }

    public function paymentUrl(WP_REST_Request $request): WP_REST_Response
    {
        $order = $this->branchOrders->find((int) $request->get_param('id'));

        if ($order === null || !$this->canAccessOrder($order)) {
            return $this->notFound();
        }

        return new WP_REST_Response(['payment_url' => $this->paymentBridge->paymentUrlForBranchOrder($order)]);
    }

    /**
     * @param mixed $raw
     * @return list<array{product_id: int, quantity_requested: int}>
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
            $quantity = (int) ($entry['quantity_requested'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            if (function_exists('wc_get_product') && wc_get_product($productId) === false) {
                continue;
            }

            $items[] = ['product_id' => $productId, 'quantity_requested' => $quantity];
        }

        return $items;
    }

    private function notFound(): WP_REST_Response
    {
        return new WP_REST_Response(['message' => __('Şube siparişi bulunamadı.', 'seviye-sube-siparis')], 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(BranchOrder $order): array
    {
        $branch = $this->branches->find($order->branchId);

        return [
            'id' => $order->id,
            'branch_id' => $order->branchId,
            'branch_name' => $branch?->name ?? (string) $order->branchId,
            'status' => $order->status->value,
            'created_by' => $order->createdByUserId,
            'note' => $order->note,
            'created_at' => $order->createdAt,
            'submitted_at' => $order->submittedAt,
            'approved_by' => $order->approvedByUserId,
            'approved_at' => $order->approvedAt,
            'rejected_reason' => $order->rejectedReason,
            'wc_order_id' => $order->wcOrderId,
            'total_paid_amount' => $order->totalPaidAmount(),
            'has_paid_portion' => $order->hasPaidPortion(),
            'items' => array_map($this->serializeItem(...), $order->items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(BranchOrderItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->productId,
            'quantity_requested' => $item->quantityRequested,
            'free_quantity_applied' => $item->freeQuantityApplied,
            'paid_quantity' => $item->paidQuantity,
            'unit_price' => $item->unitPrice,
            'paid_amount' => $item->paidAmount(),
        ];
    }
}
