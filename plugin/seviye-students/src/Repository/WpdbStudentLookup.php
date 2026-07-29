<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Students\Contracts\StudentLookupInterface;
use Seviye\Students\Contracts\StudentSummary;

/**
 * A separate, minimal adapter rather than reusing {@see WpdbStudentRepository}:
 * that class's find() returns the full Domain\Student (with EducationYear/
 * StudentStatus value objects), which would collide on method name/return
 * type with this interface's lighter {@see StudentSummary} and pulls in
 * data other modules have no business reading. Mirrors
 * Seviye\Branches\Repository\WpdbBranchLookup.
 */
final class WpdbStudentLookup implements StudentLookupInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function find(int $studentId): ?StudentSummary
    {
        $table = $this->connection->table('students');
        $sql = $this->connection->prepare(
            'SELECT id, branch_id, first_name, last_name FROM ' . $table . ' WHERE id = %d LIMIT 1',
            [$studentId]
        );

        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            return null;
        }

        return new StudentSummary(
            (int) $rows[0]['id'],
            (int) $rows[0]['branch_id'],
            (string) $rows[0]['first_name'],
            (string) $rows[0]['last_name']
        );
    }

    public function exists(int $studentId): bool
    {
        return $this->find($studentId) !== null;
    }
}
