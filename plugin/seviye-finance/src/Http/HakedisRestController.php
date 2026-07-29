<?php

declare(strict_types=1);

namespace Seviye\Finance\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Finance\Rbac\HakedisCapability;
use Seviye\Finance\Repository\HakedisRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/finance/hakedis/balance/*. Read-only - see
 * {@see \Seviye\Finance\Rbac\HakedisCapability}. Scoping mirrors
 * Seviye\Branches\Http\BranchesRestController exactly: HQ (VIEW_HAKEDIS)
 * may query any branch, branch-scoped staff (VIEW_OWN_HAKEDIS) only their
 * own.
 */
final class HakedisRestController extends AbstractRestController
{
    public function __construct(
        private readonly HakedisRepositoryInterface $hakedis,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly BranchLookupInterface $branches
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/finance/hakedis/balance/me', [
            'methods' => 'GET',
            'callback' => [$this, 'me'],
            'permission_callback' => $this->requireCapability(HakedisCapability::VIEW_OWN_HAKEDIS->value),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/finance/hakedis/balance/(?P<branch_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'balance'],
            'permission_callback' => [$this, 'canViewBalance'],
        ]);
    }

    public function me(): WP_REST_Response
    {
        $branchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        if ($branchId === null) {
            return new WP_REST_Response(['message' => __('Bir şubeye atanmadınız.', 'seviye-finance')], 404);
        }

        return new WP_REST_Response($this->serialize($branchId));
    }

    public function balance(WP_REST_Request $request): WP_REST_Response
    {
        $branchId = (int) $request->get_param('branch_id');

        if (!$this->branches->exists($branchId)) {
            return new WP_REST_Response(['message' => __('Şube bulunamadı.', 'seviye-finance')], 404);
        }

        return new WP_REST_Response($this->serialize($branchId));
    }

    public function canViewBalance(WP_REST_Request $request): bool
    {
        if (current_user_can(HakedisCapability::VIEW_HAKEDIS->value)) {
            return true;
        }

        if (!current_user_can(HakedisCapability::VIEW_OWN_HAKEDIS->value)) {
            return false;
        }

        $ownBranchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        return $ownBranchId === (int) $request->get_param('branch_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(int $branchId): array
    {
        return [
            'branch_id' => $branchId,
            'balance' => $this->hakedis->balanceForBranch($branchId),
        ];
    }
}
