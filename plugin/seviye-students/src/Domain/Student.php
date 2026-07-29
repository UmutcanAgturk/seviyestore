<?php

declare(strict_types=1);

namespace Seviye\Students\Domain;

/**
 * A persisted student. Every student belongs to exactly one branch
 * (`branchId`, enforced by a real FK to scp_branches), one education year
 * and one class - per the product specification's ÖĞRENCİ section.
 */
final class Student
{
    public function __construct(
        public readonly int $id,
        public readonly int $branchId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly EducationYear $educationYear,
        public readonly string $className,
        public readonly StudentStatus $status
    ) {
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
