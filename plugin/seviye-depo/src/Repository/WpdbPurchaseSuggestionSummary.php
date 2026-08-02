<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Depo\Contracts\PurchaseSuggestionSummaryInterface;
use Seviye\Depo\Domain\PurchaseSuggestionStatus;

/**
 * A separate, minimal adapter rather than reusing
 * {@see WpdbPurchaseSuggestionRepository}: that class's all() returns full
 * Domain\PurchaseSuggestion entities, more than a digest needs. Mirrors
 * Seviye\Finance\Repository\WpdbHakedisTotals's role.
 */
final class WpdbPurchaseSuggestionSummary implements PurchaseSuggestionSummaryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function pendingCount(): int
    {
        $table = $this->connection->table('purchase_suggestions');
        $sql = $this->connection->prepare(
            "SELECT COUNT(*) AS total FROM {$table} WHERE status = %s",
            [PurchaseSuggestionStatus::PENDING->value]
        );

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]['total']) ? (int) $rows[0]['total'] : 0;
    }
}
