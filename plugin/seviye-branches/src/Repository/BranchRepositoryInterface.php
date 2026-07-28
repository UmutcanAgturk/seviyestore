<?php

declare(strict_types=1);

namespace Seviye\Branches\Repository;

use Seviye\Branches\Domain\Branch;
use Seviye\Branches\Domain\BranchStatus;
use Seviye\Branches\Domain\CommissionRate;
use Seviye\Branches\Domain\Iban;

interface BranchRepositoryInterface
{
    public function create(
        string $name,
        string $slug,
        ?Iban $iban,
        CommissionRate $commissionRate,
        ?string $phone,
        ?string $address
    ): Branch;

    public function update(
        int $id,
        string $name,
        ?Iban $iban,
        CommissionRate $commissionRate,
        ?string $phone,
        ?string $address,
        BranchStatus $status
    ): Branch;

    public function find(int $id): ?Branch;

    public function findBySlug(string $slug): ?Branch;

    public function slugExists(string $slug, ?int $excludingId = null): bool;

    /**
     * @return list<Branch>
     */
    public function all(): array;
}
