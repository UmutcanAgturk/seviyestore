<?php

declare(strict_types=1);

namespace Seviye\Finance\Http;

use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Finance\Domain\HakedisSettlement;
use Seviye\Finance\Domain\SettlementMethod;
use Seviye\Finance\Rbac\HakedisCapability;
use Seviye\Finance\Repository\HakedisRepositoryInterface;
use Seviye\Finance\Repository\SettlementRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/finance/hakedis/balance/* and /finance/hakedis/settlements/*.
 * Scoping mirrors Seviye\Branches\Http\BranchesRestController exactly: HQ
 * (VIEW_HAKEDIS) may query any branch, branch-scoped staff (VIEW_OWN_HAKEDIS)
 * only their own - both balance() and settlements() reuse the same
 * canAccessBranchFinance() check. Recording a settlement is narrower still
 * (RECORD_SETTLEMENT) - see {@see \Seviye\Finance\Rbac\HakedisCapability}.
 *
 * "balance" in the response is the branch's *outstanding* amount (accrued
 * hakediş minus what has already been settled) - the figure a branch
 * actually cares about when checking "ne kadar param var" (cari bakiye),
 * not gross lifetime earnings. `accrued`/`settled` are included alongside
 * it for transparency, an additive extension that does not change the
 * pre-existing `balance` field's shape for already-shipped callers
 * (assets/js/hakedis-panel.js), only what it now nets against.
 */
final class HakedisRestController extends AbstractRestController
{
    public function __construct(
        private readonly HakedisRepositoryInterface $hakedis,
        private readonly SettlementRepositoryInterface $settlements,
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
            'permission_callback' => [$this, 'canAccessBranchFinance'],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/finance/hakedis/settlements', [
            'methods' => 'POST',
            'callback' => [$this, 'recordSettlement'],
            'permission_callback' => $this->requireCapability(HakedisCapability::RECORD_SETTLEMENT->value),
            'args' => [
                'branch_id' => ['required' => true, 'type' => 'integer'],
                'amount' => ['required' => true, 'type' => 'number'],
                'method' => ['required' => true, 'type' => 'string'],
                'note' => ['required' => false, 'type' => 'string'],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/finance/hakedis/settlements/(?P<branch_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'listSettlements'],
            'permission_callback' => [$this, 'canAccessBranchFinance'],
        ]);
    }

    public function me(): WP_REST_Response
    {
        $branchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        if ($branchId === null) {
            return new WP_REST_Response(['message' => __('Bir şubeye atanmadınız.', 'seviye-finance')], 404);
        }

        return new WP_REST_Response($this->serializeBalance($branchId));
    }

    public function balance(WP_REST_Request $request): WP_REST_Response
    {
        $branchId = (int) $request->get_param('branch_id');

        if (!$this->branches->exists($branchId)) {
            return new WP_REST_Response(['message' => __('Şube bulunamadı.', 'seviye-finance')], 404);
        }

        return new WP_REST_Response($this->serializeBalance($branchId));
    }

    public function recordSettlement(WP_REST_Request $request): WP_REST_Response
    {
        $branchId = (int) $request->get_param('branch_id');
        $amount = (float) $request->get_param('amount');
        $method = SettlementMethod::tryFrom((string) $request->get_param('method'));
        $note = $request->get_param('note');

        if (!$this->branches->exists($branchId)) {
            return new WP_REST_Response(['message' => __('Şube bulunamadı.', 'seviye-finance')], 404);
        }

        if ($amount <= 0.0 || $method === null) {
            $message = __('Geçersiz tutar veya ödeme yöntemi.', 'seviye-finance');

            return new WP_REST_Response(['message' => $message], 422);
        }

        $settlement = $this->settlements->record(
            $branchId,
            $amount,
            $method,
            $note !== null ? sanitize_text_field((string) $note) : null,
            get_current_user_id()
        );

        return new WP_REST_Response($this->serializeSettlement($settlement), 201);
    }

    public function listSettlements(WP_REST_Request $request): WP_REST_Response
    {
        $branchId = (int) $request->get_param('branch_id');

        if (!$this->branches->exists($branchId)) {
            return new WP_REST_Response(['message' => __('Şube bulunamadı.', 'seviye-finance')], 404);
        }

        $settlements = array_map($this->serializeSettlement(...), $this->settlements->listForBranch($branchId));

        return new WP_REST_Response($settlements);
    }

    public function canAccessBranchFinance(WP_REST_Request $request): bool
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
    private function serializeBalance(int $branchId): array
    {
        $accrued = $this->hakedis->balanceForBranch($branchId);
        $settled = $this->settlements->settledForBranch($branchId);

        return [
            'branch_id' => $branchId,
            'accrued' => $accrued,
            'settled' => $settled,
            'balance' => round($accrued - $settled, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSettlement(HakedisSettlement $settlement): array
    {
        return [
            'id' => $settlement->id,
            'branch_id' => $settlement->branchId,
            'amount' => $settlement->amount,
            'method' => $settlement->method->value,
            'note' => $settlement->note,
            'recorded_by' => $settlement->recordedByUserId,
            'created_at' => $settlement->createdAt,
        ];
    }
}
