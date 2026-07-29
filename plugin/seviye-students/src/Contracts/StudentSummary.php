<?php

declare(strict_types=1);

namespace Seviye\Students\Contracts;

/**
 * Minimal, stable read model for other modules to display alongside their
 * own data (e.g. Seviye Pricing showing which student a price rule applies
 * to) without depending on Students' full Domain\Student entity (which
 * carries EducationYear/StudentStatus value objects those modules have no
 * business knowing about). Mirrors Seviye\Branches\Contracts\BranchSummary.
 */
final class StudentSummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $branchId,
        public readonly string $firstName,
        public readonly string $lastName
    ) {
    }
}
