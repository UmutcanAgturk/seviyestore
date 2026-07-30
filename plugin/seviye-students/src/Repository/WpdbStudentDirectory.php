<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use Seviye\Students\Contracts\StudentDirectoryEntry;
use Seviye\Students\Contracts\StudentDirectoryInterface;
use Seviye\Students\Domain\Student;

/**
 * Separate, minimal adapter over {@see StudentRepositoryInterface} for the
 * published {@see StudentDirectoryInterface} Contract - mirrors the
 * WpdbStudentLookup/WpdbStudentGuardianCheck pattern (a thin adapter per
 * published Contract, never exposing the full repository to other
 * modules).
 */
final class WpdbStudentDirectory implements StudentDirectoryInterface
{
    public function __construct(private readonly StudentRepositoryInterface $students)
    {
    }

    public function all(): array
    {
        return array_map($this->toDirectoryEntry(...), $this->students->all());
    }

    private function toDirectoryEntry(Student $student): StudentDirectoryEntry
    {
        return new StudentDirectoryEntry(
            $student->id,
            $student->branchId,
            $student->firstName,
            $student->lastName,
            $student->educationYear->value(),
            $student->className,
            $student->status->value
        );
    }
}
