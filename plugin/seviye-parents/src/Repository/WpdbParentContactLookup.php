<?php

declare(strict_types=1);

namespace Seviye\Parents\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Parents\Contracts\ParentContactLookupInterface;

/**
 * A separate, minimal adapter rather than reusing {@see WpdbParentProfileRepository}:
 * that class's find() returns the full Domain\ParentProfile (notification
 * preference, KVKK consent timestamp), data other modules have no business
 * reading. Mirrors Seviye\Students\Repository\WpdbStudentLookup.
 */
final class WpdbParentContactLookup implements ParentContactLookupInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function phoneFor(int $userId): ?string
    {
        $table = $this->connection->table('parent_profiles');
        $sql = $this->connection->prepare(
            'SELECT phone FROM ' . $table . ' WHERE user_id = %d LIMIT 1',
            [$userId]
        );

        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0]) || $rows[0]['phone'] === null || $rows[0]['phone'] === '') {
            return null;
        }

        return (string) $rows[0]['phone'];
    }
}
