<?php

declare(strict_types=1);

namespace Seviye\Pricing\Tests\Fakes;

use Seviye\Students\Contracts\StudentLookupInterface;
use Seviye\Students\Contracts\StudentSummary;

final class FakeStudentLookup implements StudentLookupInterface
{
    /** @var array<int, StudentSummary> */
    public array $students = [];

    public function put(int $studentId, int $branchId): void
    {
        $this->students[$studentId] = new StudentSummary($studentId, $branchId, 'Test', 'Student');
    }

    public function find(int $studentId): ?StudentSummary
    {
        return $this->students[$studentId] ?? null;
    }

    public function exists(int $studentId): bool
    {
        return isset($this->students[$studentId]);
    }
}
