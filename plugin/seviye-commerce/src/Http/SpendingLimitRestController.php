<?php

declare(strict_types=1);

namespace Seviye\Commerce\Http;

use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Commerce\Domain\SpendingLimitPeriod;
use Seviye\Commerce\Repository\SpendingLimitRepositoryInterface;
use Seviye\Commerce\Support\StudentSpendingCalculator;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Students\Contracts\StudentLookupInterface;
use Seviye\Students\Rbac\StudentCapability;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/commerce/students/{id}/spending-limit - reuses Students'
 * scp_manage_students capability + the exact same branch-scoping shape as
 * StudentsRestController::canAccessStudent() (a Şube Müdürü may only manage
 * their own branch's students' limits; Genel Merkez/Bölge Müdürü manage
 * every student's).
 */
final class SpendingLimitRestController extends AbstractRestController
{
    public function __construct(
        private readonly SpendingLimitRepositoryInterface $limits,
        private readonly StudentSpendingCalculator $spending,
        private readonly StudentLookupInterface $students,
        private readonly BranchMembershipInterface $branchMemberships
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/commerce/students/(?P<id>\d+)/spending-limit', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'canAccessStudent'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'canAccessStudent'],
                'args' => [
                    'period' => ['required' => true, 'type' => 'string'],
                    'limit_amount' => ['required' => true, 'type' => 'number'],
                ],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'destroy'],
                'permission_callback' => [$this, 'canAccessStudent'],
            ],
        ]);
    }

    public function canAccessStudent(WP_REST_Request $request): bool
    {
        if (!current_user_can(StudentCapability::MANAGE_STUDENTS->value)) {
            return false;
        }

        $branchId = $this->branchMemberships->branchIdForUser(get_current_user_id());

        if ($branchId === null) {
            return true;
        }

        $student = $this->students->find((int) $request->get_param('id'));

        return $student !== null && $student->branchId === $branchId;
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $studentId = (int) $request->get_param('id');
        $limit = $this->limits->find($studentId);

        if ($limit === null) {
            return new WP_REST_Response([
                'student_id' => $studentId,
                'period' => null,
                'limit_amount' => null,
                'spent_amount' => null,
                'remaining_amount' => null,
            ]);
        }

        $spent = $this->spending->spentAmount($studentId, $limit->period);

        return new WP_REST_Response([
            'student_id' => $studentId,
            'period' => $limit->period->value,
            'limit_amount' => $limit->limitAmount,
            'spent_amount' => $spent,
            'remaining_amount' => max($limit->limitAmount - $spent, 0.0),
        ]);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $studentId = (int) $request->get_param('id');
        $period = SpendingLimitPeriod::tryFrom((string) $request->get_param('period'));
        $limitAmount = (float) $request->get_param('limit_amount');

        if ($period === null || $limitAmount <= 0) {
            return new WP_REST_Response(
                ['message' => __('Geçerli bir dönem ve limit tutarı gerekli.', 'seviye-commerce')],
                422
            );
        }

        $limit = $this->limits->set($studentId, $period, $limitAmount);
        $spent = $this->spending->spentAmount($studentId, $limit->period);

        return new WP_REST_Response([
            'student_id' => $studentId,
            'period' => $limit->period->value,
            'limit_amount' => $limit->limitAmount,
            'spent_amount' => $spent,
            'remaining_amount' => max($limit->limitAmount - $spent, 0.0),
        ]);
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        $this->limits->delete((int) $request->get_param('id'));

        return new WP_REST_Response(['success' => true]);
    }
}
