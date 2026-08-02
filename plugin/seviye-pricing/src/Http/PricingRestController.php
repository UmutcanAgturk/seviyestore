<?php

declare(strict_types=1);

namespace Seviye\Pricing\Http;

use InvalidArgumentException;
use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Pricing\Domain\Money;
use Seviye\Pricing\Domain\PriceRule;
use Seviye\Pricing\Domain\PriceRuleStatus;
use Seviye\Pricing\Domain\PriceScope;
use Seviye\Pricing\Domain\PriceScopeType;
use Seviye\Pricing\Rbac\PricingCapability;
use Seviye\Pricing\Repository\PriceRuleRepositoryInterface;
use Seviye\Pricing\Support\PriceRuleImportParser;
use Seviye\Students\Contracts\StudentLookupInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/pricing/rules/*. "Manage" access is scoped at request time, not
 * by capability alone, exactly like Students' StudentsRestController: a
 * user with a branch membership (Şube Müdürü) may only touch general-free
 * rules within their own branch (their own branch's rule, or a rule for one
 * of their own branch's students); a user without one (Genel Merkez, Bölge
 * Müdürü) may touch everything, including platform-wide GENERAL rules.
 */
final class PricingRestController extends AbstractRestController
{
    public function __construct(
        private readonly PriceRuleRepositoryInterface $rules,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly BranchLookupInterface $branches,
        private readonly StudentLookupInterface $students,
        private readonly PriceRuleImportParser $importParser
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/pricing/rules', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => $this->requireCapability(PricingCapability::MANAGE_PRICING->value),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => $this->requireCapability(PricingCapability::MANAGE_PRICING->value),
                'args' => $this->writableArgs(),
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/pricing/rules/import', [
            'methods' => 'POST',
            'callback' => [$this, 'import'],
            'permission_callback' => $this->requireCapability(PricingCapability::MANAGE_PRICING->value),
            'args' => ['csv' => ['required' => true, 'type' => 'string']],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/pricing/rules/(?P<id>\d+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'canAccessRule'],
                'args' => [
                    'price' => ['required' => true, 'type' => 'number'],
                    'status' => ['required' => false, 'type' => 'string'],
                ],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete'],
                'permission_callback' => [$this, 'canAccessRule'],
            ],
        ]);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $productId = (int) $request->get_param('product_id');

        if ($productId <= 0) {
            return new WP_REST_Response(['message' => __('product_id zorunludur.', 'seviye-pricing')], 422);
        }

        $rules = $this->rules->forProduct($productId);
        $ownBranchId = $this->currentUserBranchId();

        if ($ownBranchId !== null) {
            $rules = array_values(array_filter(
                $rules,
                fn (PriceRule $rule): bool => $this->visibleToOwnBranch($rule, $ownBranchId)
            ));
        }

        return new WP_REST_Response(array_map($this->serialize(...), $rules));
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $productId = (int) $request->get_param('product_id');
        $scopeType = PriceScopeType::tryFrom((string) $request->get_param('scope'));

        if ($productId <= 0 || $scopeType === null) {
            return new WP_REST_Response(['message' => __('Geçersiz ürün veya fiyat kapsamı.', 'seviye-pricing')], 422);
        }

        $result = $this->createRule(
            $productId,
            $scopeType,
            (int) $request->get_param('target_id'),
            (float) $request->get_param('price')
        );

        if (isset($result['rule'])) {
            return new WP_REST_Response($this->serialize($result['rule']), 201);
        }

        return new WP_REST_Response(['message' => $result['message']], $result['status']);
    }

    /**
     * "Toplu fiyat kuralı içe aktarma" - reuses createRule(), the exact same
     * validation/creation path store() uses per row (scope resolution,
     * RBAC scope check, duplicate-active-rule check, taban fiyat floor),
     * so a CSV row can never bypass any rule a single manual POST would
     * enforce. Response shape mirrors
     * Seviye\Students\Http\StudentsRestController::import() exactly.
     */
    public function import(WP_REST_Request $request): WP_REST_Response
    {
        $rows = $this->importParser->parse((string) $request->get_param('csv'));

        if ($rows === []) {
            return new WP_REST_Response(['message' => __('CSV içeriği boş veya okunamadı.', 'seviye-pricing')], 422);
        }

        $imported = [];
        $errors = [];

        foreach ($rows as $row) {
            if ($row->error !== null) {
                $errors[] = ['line' => $row->lineNumber, 'message' => $row->error];
                continue;
            }

            $productId = (int) $row->productId;
            $scopeType = PriceScopeType::from($row->scope);
            $targetId = $row->targetId !== null ? (int) $row->targetId : 0;
            $price = (float) str_replace(',', '.', $row->price);

            if ($productId <= 0) {
                $errors[] = ['line' => $row->lineNumber, 'message' => __('Geçersiz ürün kimliği.', 'seviye-pricing')];
                continue;
            }

            $result = $this->createRule($productId, $scopeType, $targetId, $price);

            if (isset($result['rule'])) {
                $imported[] = $this->serialize($result['rule']);
            } else {
                $errors[] = ['line' => $row->lineNumber, 'message' => $result['message']];
            }
        }

        return new WP_REST_Response([
            'imported_count' => count($imported),
            'error_count' => count($errors),
            'imported' => $imported,
            'errors' => $errors,
        ]);
    }

    /**
     * Shared by store() and import() - the ONE place a price rule is
     * actually created, so a CSV row is held to exactly the same rules a
     * manual POST is (scope resolution/RBAC/duplicate/taban fiyat floor).
     *
     * @return array{rule: PriceRule}|array{message: string, status: int}
     */
    private function createRule(int $productId, PriceScopeType $scopeType, int $targetId, float $rawPrice): array
    {
        $scope = $this->resolveScopeForWrite($scopeType, $targetId);

        if ($scope === null) {
            return ['message' => __('Geçersiz şube veya öğrenci kimliği.', 'seviye-pricing'), 'status' => 422];
        }

        if (!$this->canWriteScope($scope)) {
            $message = __('Bu kapsamda fiyat kuralı oluşturma yetkiniz yok.', 'seviye-pricing');

            return ['message' => $message, 'status' => 403];
        }

        if (!$this->scopeTargetIsValid($scope)) {
            return ['message' => __('Geçersiz şube veya öğrenci.', 'seviye-pricing'), 'status' => 422];
        }

        if ($this->rules->activeRuleExists($productId, $scope)) {
            $message = __('Bu ürün için bu kapsamda zaten aktif bir fiyat kuralı var.', 'seviye-pricing');

            return ['message' => $message, 'status' => 409];
        }

        try {
            $price = Money::fromFloat($rawPrice);
        } catch (InvalidArgumentException $exception) {
            return ['message' => $exception->getMessage(), 'status' => 422];
        }

        $floorViolation = $this->violatesBasePriceFloor($productId, $scope, $price);

        if ($floorViolation !== null) {
            return ['message' => $floorViolation, 'status' => 422];
        }

        return ['rule' => $this->rules->create($productId, $scope, $price)];
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $existing = $this->rules->find($id);

        if ($existing === null) {
            return new WP_REST_Response(['message' => __('Fiyat kuralı bulunamadı.', 'seviye-pricing')], 404);
        }

        $status = PriceRuleStatus::tryFrom((string) ($request->get_param('status') ?? PriceRuleStatus::ACTIVE->value));

        if ($status === null) {
            return new WP_REST_Response(['message' => __('Geçersiz durum.', 'seviye-pricing')], 422);
        }

        try {
            $price = Money::fromFloat((float) $request->get_param('price'));
        } catch (InvalidArgumentException $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 422);
        }

        $floorViolation = $this->violatesBasePriceFloor($existing->productId, $existing->scope, $price);

        if ($floorViolation !== null) {
            return new WP_REST_Response(['message' => $floorViolation], 422);
        }

        $rule = $this->rules->update($id, $price, $status);

        return new WP_REST_Response($this->serialize($rule));
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        if ($this->rules->find($id) === null) {
            return new WP_REST_Response(['message' => __('Fiyat kuralı bulunamadı.', 'seviye-pricing')], 404);
        }

        $this->rules->delete($id);

        return new WP_REST_Response(['success' => true]);
    }

    public function canAccessRule(WP_REST_Request $request): bool
    {
        if (!current_user_can(PricingCapability::MANAGE_PRICING->value)) {
            return false;
        }

        $rule = $this->rules->find((int) $request->get_param('id'));

        if ($rule === null) {
            // HQ still reaches the handler for a clean 404; branch-scoped
            // staff get a uniform 403 instead of leaking whether the id
            // exists outside their own branch.
            return $this->currentUserBranchId() === null;
        }

        return $this->canWriteScope($rule->scope);
    }

    /**
     * Branch-scoped staff (Şube Müdürü) always write BRANCH-scoped rules
     * into their own branch, regardless of what the request body says (the
     * only value canWriteScope() would ever accept from them anyway) -
     * mirrors Students' resolveBranchIdForWrite(). HQ must supply a
     * target_id for BRANCH/STUDENT scopes. Returns null on a missing or
     * non-positive target_id where one is required.
     */
    private function resolveScopeForWrite(PriceScopeType $scopeType, int $targetId): ?PriceScope
    {
        $ownBranchId = $this->currentUserBranchId();

        if ($scopeType === PriceScopeType::BRANCH && $ownBranchId !== null) {
            return PriceScope::forBranch($ownBranchId);
        }

        try {
            return match ($scopeType) {
                PriceScopeType::GENERAL => PriceScope::general(),
                PriceScopeType::BRANCH => PriceScope::forBranch($targetId),
                PriceScopeType::STUDENT => PriceScope::forStudent($targetId),
            };
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function scopeTargetIsValid(PriceScope $scope): bool
    {
        return match ($scope->type) {
            PriceScopeType::GENERAL => true,
            PriceScopeType::BRANCH => $this->branches->exists((int) $scope->branchId),
            PriceScopeType::STUDENT => $this->students->exists((int) $scope->studentId),
        };
    }

    /**
     * Branch-scoped staff (Şube Müdürü) may only write GENERAL-free rules
     * anchored to their own branch; HQ (no membership row) may write any
     * BRANCH/STUDENT scope. GENERAL is narrower still - not just "no branch
     * membership" but the explicit MANAGE_BASE_PRICING capability, so Bölge
     * Müdürü (which also has no membership row) cannot touch it even though
     * it can touch every other scope.
     */
    private function canWriteScope(PriceScope $scope): bool
    {
        if ($scope->type === PriceScopeType::GENERAL) {
            return current_user_can(PricingCapability::MANAGE_BASE_PRICING->value);
        }

        $ownBranchId = $this->currentUserBranchId();

        if ($ownBranchId === null) {
            return true;
        }

        return match ($scope->type) {
            PriceScopeType::BRANCH => $scope->branchId === $ownBranchId,
            PriceScopeType::STUDENT => $this->students->find((int) $scope->studentId)?->branchId === $ownBranchId,
        };
    }

    /**
     * "Genel merkezin belirlediği fiyatın aşağısına fiyat verilemez" - a
     * BRANCH/STUDENT rule may never undercut its product's own active
     * GENERAL rule (the floor Genel Merkez/Sistem set - see
     * MANAGE_BASE_PRICING). Checked per product (not a single platform-wide
     * floor), and only when a GENERAL rule actually exists for that product
     * - nothing to violate otherwise. Returns the error message to show, or
     * null when the price is acceptable.
     */
    private function violatesBasePriceFloor(int $productId, PriceScope $scope, Money $price): ?string
    {
        if ($scope->type === PriceScopeType::GENERAL) {
            return null;
        }

        $floor = $this->rules->activeRuleFor($productId, PriceScope::general());

        if ($floor === null || $price->toFloat() >= $floor->price->toFloat()) {
            return null;
        }

        return __('Fiyat, Genel Merkez tarafından belirlenen taban fiyatın altında olamaz.', 'seviye-pricing');
    }

    private function visibleToOwnBranch(PriceRule $rule, int $ownBranchId): bool
    {
        return match ($rule->scope->type) {
            PriceScopeType::GENERAL => true,
            PriceScopeType::BRANCH => $rule->scope->branchId === $ownBranchId,
            PriceScopeType::STUDENT => $this->students->find((int) $rule->scope->studentId)?->branchId === $ownBranchId,
        };
    }

    private function currentUserBranchId(): ?int
    {
        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PriceRule $rule): array
    {
        return [
            'id' => $rule->id,
            'product_id' => $rule->productId,
            'scope' => $rule->scope->type->value,
            'branch_id' => $rule->scope->branchId,
            'student_id' => $rule->scope->studentId,
            'price' => $rule->price->toFloat(),
            'status' => $rule->status->value,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function writableArgs(): array
    {
        return [
            'product_id' => ['required' => true, 'type' => 'integer'],
            'scope' => ['required' => true, 'type' => 'string'],
            'target_id' => ['required' => false, 'type' => 'integer'],
            'price' => ['required' => true, 'type' => 'number'],
        ];
    }
}
