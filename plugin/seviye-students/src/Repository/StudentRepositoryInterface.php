<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use Seviye\Students\Domain\EducationYear;
use Seviye\Students\Domain\Student;
use Seviye\Students\Domain\StudentStatus;

interface StudentRepositoryInterface
{
    public function create(
        int $branchId,
        string $firstName,
        string $lastName,
        EducationYear $educationYear,
        string $className,
        ?string $tcNo = null
    ): Student;

    public function update(
        int $id,
        int $branchId,
        string $firstName,
        string $lastName,
        EducationYear $educationYear,
        string $className,
        StudentStatus $status,
        ?string $tcNo = null
    ): Student;

    public function find(int $id): ?Student;

    /**
     * @return list<Student>
     */
    public function all(): array;

    /**
     * @return list<Student>
     */
    public function findByBranch(int $branchId): array;
}
