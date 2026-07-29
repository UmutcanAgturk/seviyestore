<?php

declare(strict_types=1);

namespace Seviye\Branches\Http;

use InvalidArgumentException;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Branches\Domain\Branch;
use Seviye\Branches\Domain\BranchStatus;
use Seviye\Branches\Domain\CommissionRate;
use Seviye\Branches\Domain\Iban;
use Seviye\Branches\Domain\Slug;
use Seviye\Branches\Rbac\BranchCapability;
use Seviye\Branches\Repository\BranchRepositoryInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/branches/*. List/create/update are Genel Merkez + Bölge Müdürü
 * only (MANAGE_BRANCHES); a single branch can also be read by the staff
 * member assigned to it (VIEW_OWN_BRANCH), never by staff of another branch.
 */
final class BranchesRestController extends AbstractRestController
{
    public function __construct(
        private readonly BranchRepositoryInterface $branches,
        private readonly BranchMembershipInterface $memberships
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/branches', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => $this->requireCapability(BranchCapability::MANAGE_BRANCHES->value),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => $this->requireCapability(BranchCapability::MANAGE_BRANCHES->value),
                'args' => $this->writableArgs(),
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/branches/me', [
            'methods' => 'GET',
            'callback' => [$this, 'me'],
            'permission_callback' => $this->requireCapability(BranchCapability::VIEW_OWN_BRANCH->value),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/branches/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'canViewBranch'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => $this->requireCapability(BranchCapability::MANAGE_BRANCHES->value),
                'args' => $this->writableArgs(),
            ],
        ]);
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response(array_map($this->serialize(...), $this->branches->all()));
    }

    public function me(): WP_REST_Response
    {
        $branchId = $this->memberships->branchIdForUser(get_current_user_id());
        $branch = $branchId !== null ? $this->branches->find($branchId) : null;

        if ($branch === null) {
            return new WP_REST_Response(['message' => __('Bir şubeye atanmadınız.', 'seviye-branches')], 404);
        }

        return new WP_REST_Response($this->serialize($branch));
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $branch = $this->branches->find((int) $request->get_param('id'));

        if ($branch === null) {
            return new WP_REST_Response(['message' => __('Şube bulunamadı.', 'seviye-branches')], 404);
        }

        return new WP_REST_Response($this->serialize($branch));
    }

    public function canViewBranch(WP_REST_Request $request): bool
    {
        if (current_user_can(BranchCapability::MANAGE_BRANCHES->value)) {
            return true;
        }

        if (!current_user_can(BranchCapability::VIEW_OWN_BRANCH->value)) {
            return false;
        }

        return $this->memberships->branchIdForUser(get_current_user_id()) === (int) $request->get_param('id');
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $branch = $this->branches->create(
                (string) $request->get_param('name'),
                $this->resolveNewSlug($request),
                $this->resolveIban($request),
                CommissionRate::fromPercentage((float) $request->get_param('commission_rate')),
                $this->stringOrNull($request->get_param('phone')),
                $this->stringOrNull($request->get_param('address'))
            );
        } catch (InvalidArgumentException $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 422);
        }

        return new WP_REST_Response($this->serialize($branch), 201);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        if ($this->branches->find($id) === null) {
            return new WP_REST_Response(['message' => __('Şube bulunamadı.', 'seviye-branches')], 404);
        }

        try {
            $branch = $this->branches->update(
                $id,
                (string) $request->get_param('name'),
                $this->resolveIban($request),
                CommissionRate::fromPercentage((float) $request->get_param('commission_rate')),
                $this->stringOrNull($request->get_param('phone')),
                $this->stringOrNull($request->get_param('address')),
                BranchStatus::from((string) ($request->get_param('status') ?? BranchStatus::ACTIVE->value))
            );
        } catch (InvalidArgumentException $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 422);
        }

        return new WP_REST_Response($this->serialize($branch));
    }

    /**
     * Slug is set once at creation and never changed afterwards (stable
     * identifier - see {@see BranchRepositoryInterface::update()}, which
     * deliberately has no slug parameter).
     */
    private function resolveNewSlug(WP_REST_Request $request): string
    {
        $requested = $this->stringOrNull($request->get_param('slug'));
        $slug = Slug::fromString($requested ?? (string) $request->get_param('name'));

        if ($this->branches->slugExists($slug)) {
            $slug .= '-' . substr(md5(uniqid('', true)), 0, 6);
        }

        return $slug;
    }

    private function resolveIban(WP_REST_Request $request): ?Iban
    {
        $raw = $this->stringOrNull($request->get_param('iban'));

        return $raw !== null ? Iban::fromString($raw) : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'slug' => $branch->slug,
            'iban' => $branch->iban?->value(),
            'commission_rate' => $branch->commissionRate->percentage(),
            'phone' => $branch->phone,
            'address' => $branch->address,
            'status' => $branch->status->value,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function writableArgs(): array
    {
        return [
            'name' => ['required' => true, 'type' => 'string'],
            'slug' => ['required' => false, 'type' => 'string'],
            'iban' => ['required' => false, 'type' => 'string'],
            'commission_rate' => ['required' => true, 'type' => 'number'],
            'phone' => ['required' => false, 'type' => 'string'],
            'address' => ['required' => false, 'type' => 'string'],
            'status' => ['required' => false, 'type' => 'string'],
        ];
    }
}
