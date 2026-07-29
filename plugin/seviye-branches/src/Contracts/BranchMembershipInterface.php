<?php

declare(strict_types=1);

namespace Seviye\Branches\Contracts;

/**
 * Published contract: which branch a staff user ("Yetkili") belongs to.
 * HQ-level roles have no membership row at all - {@see branchIdForUser()}
 * returning null for them is expected, not an error.
 *
 * This is the boundary other modules are allowed to depend on (e.g. Seviye
 * Students scoping a Şube Müdürü's queries to their own branch) - never on
 * Branches' internal Repository/Domain classes. See docs/ARCHITECTURE.md,
 * "Kural" under the layer diagram.
 */
interface BranchMembershipInterface
{
    public function assign(int $userId, int $branchId): void;

    public function branchIdForUser(int $userId): ?int;

    public function unassign(int $userId): void;
}
