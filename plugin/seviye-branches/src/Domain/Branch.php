<?php

declare(strict_types=1);

namespace Seviye\Branches\Domain;

/**
 * A persisted branch. Immutable snapshot returned by
 * {@see \Seviye\Branches\Repository\BranchRepositoryInterface} - callers
 * that want to change a branch call the repository again, they don't mutate
 * this object.
 */
final class Branch
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?Iban $iban,
        public readonly CommissionRate $commissionRate,
        public readonly ?string $phone,
        public readonly ?string $address,
        public readonly BranchStatus $status
    ) {
    }
}
