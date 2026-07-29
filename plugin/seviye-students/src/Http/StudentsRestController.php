<?php

declare(strict_types=1);

namespace Seviye\Students\Http;

use InvalidArgumentException;
use Seviye\Branches\Contracts\BranchLookupInterface;
use Seviye\Branches\Contracts\BranchMembershipInterface;
use Seviye\Core\Http\AbstractRestController;
use Seviye\Core\Http\RestApiRegistrar;
use Seviye\Students\Domain\EducationYear;
use Seviye\Students\Domain\ParentRelationship;
use Seviye\Students\Domain\Student;
use Seviye\Students\Domain\StudentStatus;
use Seviye\Students\Rbac\StudentCapability;
use Seviye\Students\Repository\StudentParentRepositoryInterface;
use Seviye\Students\Repository\StudentRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * seviye/v1/students/*. "Manage" access is scoped at request time, not by
 * capability alone: a user with a branch membership (Şube Müdürü) only ever
 * sees/writes their own branch's students; a user without one (Genel
 * Merkez, Bölge Müdürü) sees/writes every branch. See {@see StudentCapability}.
 */
final class StudentsRestController extends AbstractRestController
{
    public function __construct(
        private readonly StudentRepositoryInterface $students,
        private readonly StudentParentRepositoryInterface $studentParents,
        private readonly BranchMembershipInterface $branchMemberships,
        private readonly BranchLookupInterface $branchLookup
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestApiRegistrar::NAMESPACE, '/students', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => $this->requireCapability(StudentCapability::MANAGE_STUDENTS->value),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'store'],
                'permission_callback' => $this->requireCapability(StudentCapability::MANAGE_STUDENTS->value),
                'args' => $this->writableArgs(),
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/students/mine', [
            'methods' => 'GET',
            'callback' => [$this, 'mine'],
            'permission_callback' => $this->requireCapability(StudentCapability::VIEW_OWN_CHILDREN->value),
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/students/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'canAccessStudent'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'canAccessStudent'],
                'args' => $this->writableArgs(),
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/students/(?P<id>\d+)/parents', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listParents'],
                'permission_callback' => [$this, 'canAccessStudent'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'linkParent'],
                'permission_callback' => [$this, 'canAccessStudent'],
                'args' => [
                    'parent_user_id' => ['required' => true, 'type' => 'integer'],
                    'relationship' => ['required' => true, 'type' => 'string'],
                ],
            ],
        ]);

        register_rest_route(RestApiRegistrar::NAMESPACE, '/students/(?P<id>\d+)/parents/(?P<parent_user_id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'unlinkParent'],
            'permission_callback' => [$this, 'canAccessStudent'],
        ]);
    }

    public function index(): WP_REST_Response
    {
        $branchId = $this->currentUserBranchId();
        $students = $branchId !== null ? $this->students->findByBranch($branchId) : $this->students->all();

        return new WP_REST_Response(array_map($this->serialize(...), $students));
    }

    public function mine(): WP_REST_Response
    {
        $studentIds = $this->studentParents->studentIdsForParent(get_current_user_id());
        $students = array_filter(array_map($this->students->find(...), $studentIds));

        return new WP_REST_Response(array_map($this->serialize(...), array_values($students)));
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $student = $this->students->find((int) $request->get_param('id'));

        if ($student === null) {
            return new WP_REST_Response(['message' => __('Öğrenci bulunamadı.', 'seviye-students')], 404);
        }

        return new WP_REST_Response($this->serialize($student));
    }

    public function canAccessStudent(WP_REST_Request $request): bool
    {
        if (!current_user_can(StudentCapability::MANAGE_STUDENTS->value)) {
            return false;
        }

        $branchId = $this->currentUserBranchId();

        if ($branchId === null) {
            return true;
        }

        $student = $this->students->find((int) $request->get_param('id'));

        return $student !== null && $student->branchId === $branchId;
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $branchId = $this->resolveBranchIdForWrite($request);

        if ($branchId === null || !$this->branchLookup->exists($branchId)) {
            return new WP_REST_Response(['message' => __('Geçersiz şube.', 'seviye-students')], 422);
        }

        try {
            $student = $this->students->create(
                $branchId,
                (string) $request->get_param('first_name'),
                (string) $request->get_param('last_name'),
                EducationYear::fromString((string) $request->get_param('education_year')),
                (string) $request->get_param('class_name')
            );
        } catch (InvalidArgumentException $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 422);
        }

        return new WP_REST_Response($this->serialize($student), 201);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $branchId = $this->resolveBranchIdForWrite($request);

        if ($branchId === null || !$this->branchLookup->exists($branchId)) {
            return new WP_REST_Response(['message' => __('Geçersiz şube.', 'seviye-students')], 422);
        }

        try {
            $student = $this->students->update(
                $id,
                $branchId,
                (string) $request->get_param('first_name'),
                (string) $request->get_param('last_name'),
                EducationYear::fromString((string) $request->get_param('education_year')),
                (string) $request->get_param('class_name'),
                StudentStatus::from((string) ($request->get_param('status') ?? StudentStatus::ACTIVE->value))
            );
        } catch (InvalidArgumentException $exception) {
            return new WP_REST_Response(['message' => $exception->getMessage()], 422);
        }

        return new WP_REST_Response($this->serialize($student));
    }

    public function listParents(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->studentParents->parentUserIdsForStudent((int) $request->get_param('id')));
    }

    public function linkParent(WP_REST_Request $request): WP_REST_Response
    {
        $relationship = ParentRelationship::tryFrom((string) $request->get_param('relationship'));

        if ($relationship === null) {
            return new WP_REST_Response(['message' => __('Geçersiz veli ilişki türü.', 'seviye-students')], 422);
        }

        $this->studentParents->link(
            (int) $request->get_param('id'),
            (int) $request->get_param('parent_user_id'),
            $relationship
        );

        return new WP_REST_Response(['success' => true]);
    }

    public function unlinkParent(WP_REST_Request $request): WP_REST_Response
    {
        $this->studentParents->unlink((int) $request->get_param('id'), (int) $request->get_param('parent_user_id'));

        return new WP_REST_Response(['success' => true]);
    }

    /**
     * Branch-scoped staff (Şube Müdürü) always write into their own branch,
     * regardless of what the request body says; HQ must supply one.
     */
    private function resolveBranchIdForWrite(WP_REST_Request $request): ?int
    {
        $ownBranchId = $this->currentUserBranchId();

        if ($ownBranchId !== null) {
            return $ownBranchId;
        }

        $requested = $request->get_param('branch_id');

        return $requested !== null ? (int) $requested : null;
    }

    private function currentUserBranchId(): ?int
    {
        return $this->branchMemberships->branchIdForUser(get_current_user_id());
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Student $student): array
    {
        return [
            'id' => $student->id,
            'branch_id' => $student->branchId,
            'branch_name' => $this->branchLookup->find($student->branchId)?->name,
            'first_name' => $student->firstName,
            'last_name' => $student->lastName,
            'education_year' => $student->educationYear->value(),
            'class_name' => $student->className,
            'status' => $student->status->value,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function writableArgs(): array
    {
        return [
            'branch_id' => ['required' => false, 'type' => 'integer'],
            'first_name' => ['required' => true, 'type' => 'string'],
            'last_name' => ['required' => true, 'type' => 'string'],
            'education_year' => ['required' => true, 'type' => 'string'],
            'class_name' => ['required' => true, 'type' => 'string'],
            'status' => ['required' => false, 'type' => 'string'],
        ];
    }
}
