<?php

declare(strict_types=1);

namespace Seviye\Branches\Repository;

/**
 * Which branch a staff user ("Yetkili") belongs to. HQ-level roles have no
 * membership row at all - {@see branchIdForUser()} returning null for them
 * is expected, not an error.
 */
interface BranchMembershipRepositoryInterface
{
    public function assign(int $userId, int $branchId): void;

    public function branchIdForUser(int $userId): ?int;

    public function unassign(int $userId): void;
}
