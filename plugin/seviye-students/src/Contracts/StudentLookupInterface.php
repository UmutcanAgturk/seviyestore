<?php

declare(strict_types=1);

namespace Seviye\Students\Contracts;

/**
 * Published contract for read-only student lookups by other modules - the
 * boundary other modules are allowed to depend on (e.g. Seviye Pricing
 * validating a student-scoped price rule and deriving that student's
 * branch), never on Students' internal Repository/Domain classes. See
 * docs/ARCHITECTURE.md, "Kural" under the layer diagram.
 */
interface StudentLookupInterface
{
    public function find(int $studentId): ?StudentSummary;

    public function exists(int $studentId): bool;
}
