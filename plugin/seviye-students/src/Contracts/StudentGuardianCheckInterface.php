<?php

declare(strict_types=1);

namespace Seviye\Students\Contracts;

/**
 * Published contract: whether a WordPress user is a registered guardian of
 * a given student - the boundary other modules are allowed to depend on
 * (e.g. Seviye Commerce validating which of a Veli's own children a cart
 * item may be priced for), never on Students' internal
 * Repository\StudentParentRepositoryInterface. See docs/ARCHITECTURE.md,
 * "Kural" under the layer diagram.
 */
interface StudentGuardianCheckInterface
{
    public function isGuardianOf(int $parentUserId, int $studentId): bool;
}
