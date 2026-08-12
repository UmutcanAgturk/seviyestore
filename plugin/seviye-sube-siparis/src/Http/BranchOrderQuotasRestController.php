<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\SubeSiparis\Domain\BranchOrderQuota;
use Seviye\SubeSiparis\Rbac\BranchOrderCapability;
use Seviye\SubeSiparis\Repository\BranchOrderQuotaRepositoryInterface;
use Seviye\SubeSiparis\Repository\BranchOrderRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/sube-siparis/quotas/* - Genel Merkez'in her (şube, ürün) çifti
 * için belirlediği ücretsiz hak. "Sayıları Genel Merkez her ürün için ayrı
 * ayrı belirlesin" - yazma yalnızca MANAGE_BRANCH_ORDERS'ta (Genel Merkez/
 * Bölge Müdürü); Şube Müdürü yalnızca KENDİ şubesinin kotasını (ve o
 * kotanın ne kadarının tüketildiğini) salt okunur görür.
 */
final class BranchOrderQuotasRestController extends AbstractRestController
{
    public function __construct(
        private readonly BranchOrderQuotaRepositoryInterface $quotas,
        private readonly BranchOrderRepositoryInterface $branchOrders,
        private readonly BranchLookupInterface $branches,
        private readonly BranchMembershipInterface $branchMemberships
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/sube-siparis/quotas', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'canAccessQuotas'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => $this->requireCapability(BranchOrderCapability::MANAGE_BRANCH_ORDERS->value),
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/sube-siparis/quotas/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'destroy'],
            'permission_callback' => $this->requireCapability(BranchOrderCapability::MANAGE_BRANCH_ORDERS->value),
        ]);
    }

    public function canAccessQuotas(): bool
    {
        return current_user_can(BranchOrderCapability::MANAGE_BRANCH_ORDERS->value)
            || current_user_can(BranchOrderCapability::MANAGE_OWN_BRANCH_ORDERS->value);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        if (current_user_can(BranchOrderCapability::MANAGE_BRANCH_ORDERS->value)) {
            $raw = $request->get_param('branch_id');
            $branchId = $raw !== null && $raw !== '' ? (int) $raw : null;
            $quotas = $this->quotas->all($branchId);
        } else {
            $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());
            $quotas = $ownBranchId !== null ? $this->quotas->all($ownBranchId) : [];
        }

        return new WP_REST_Response(array_map($this->serialize(...), $quotas));
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $branchId = (int) $request->get_param('branch_id');
        $productId = (int) $request->get_param('product_id');
        $freeQuantity = (int) $request->get_param('free_quantity');

        if ($branchId <= 0 || !$this->branches->exists($branchId)) {
            return new WP_REST_Response(['message' => __('Geçersiz şube.', 'seviye-sube-siparis')], 422);
        }

        if ($productId <= 0 || (function_exists('wc_get_product') && wc_get_product($productId) === false)) {
            return new WP_REST_Response(['message' => __('Geçersiz ürün.', 'seviye-sube-siparis')], 422);
        }

        if ($freeQuantity < 0) {
            return new WP_REST_Response(['message' => __('Ücretsiz hak negatif olamaz.', 'seviye-sube-siparis')], 422);
        }

        $quota = $this->quotas->upsert($branchId, $productId, $freeQuantity);

        return new WP_REST_Response($this->serialize($quota), 201);
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        $this->quotas->delete((int) $request->get_param('id'));

        return new WP_REST_Response(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(BranchOrderQuota $quota): array
    {
        $branch = $this->branches->find($quota->branchId);
        $consumed = $this->branchOrders->consumedFreeQuantity($quota->branchId, $quota->productId);

        return [
            'id' => $quota->id,
            'branch_id' => $quota->branchId,
            'branch_name' => $branch?->name ?? (string) $quota->branchId,
            'product_id' => $quota->productId,
            'free_quantity' => $quota->freeQuantity,
            'consumed_quantity' => $consumed,
            'remaining_quantity' => max(0, $quota->freeQuantity - $consumed),
        ];
    }
}
