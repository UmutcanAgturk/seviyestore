<?php

declare(strict_types=1);

namespace Seviye\Branches\Contracts;

/**
 * Minimal, stable read model for other modules to display alongside their
 * own data (e.g. a student's branch name) without depending on Branches'
 * full Domain\Branch entity (which carries Iban/CommissionRate value
 * objects those modules have no business knowing about).
 */
final class BranchSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug
    ) {
    }
}
