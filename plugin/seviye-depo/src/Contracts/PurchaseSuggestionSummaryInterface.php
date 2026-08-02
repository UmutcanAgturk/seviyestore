<?php

declare(strict_types=1);

namespace Seviye\Depo\Contracts;

/**
 * Published contract for other modules that need a coarse count of
 * unresolved purchase suggestions (currently only Seviye Notifications'
 * weekly digest) - distinct from PurchaseSuggestionRepositoryInterface,
 * which returns full domain entities and belongs to Depo alone. Mirrors
 * Seviye\Finance\Contracts\HakedisTotalsInterface's role.
 */
interface PurchaseSuggestionSummaryInterface
{
    /** How many purchase suggestions are still PENDING (not dismissed/converted). */
    public function pendingCount(): int;
}
