<?php

declare(strict_types=1);

namespace Seviye\Depo\Contracts;

/**
 * Minimal, stable read model for other modules to display alongside their
 * own data (e.g. Reports' "Depo Raporları" showing a supplier's name next
 * to its purchase totals) without depending on Depo's full Domain\Supplier
 * entity. Mirrors Seviye\Branches\Contracts\BranchSummary's role exactly.
 */
final class SupplierSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $name
    ) {
    }
}
