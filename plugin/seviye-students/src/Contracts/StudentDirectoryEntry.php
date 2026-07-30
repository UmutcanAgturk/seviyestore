<?php

declare(strict_types=1);

namespace Seviye\Students\Contracts;

/**
 * A richer, admin-facing read model than {@see StudentSummary} - carries
 * class/education year/status, the fields a directory listing needs but a
 * cross-module "which student is this" lookup (Pricing, Commerce) never
 * does. Published for {@see StudentDirectoryInterface} consumers only.
 */
final class StudentDirectoryEntry
{
    public function __construct(
        public readonly int $id,
        public readonly int $branchId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $educationYear,
        public readonly string $className,
        public readonly string $status
    ) {
    }
}
