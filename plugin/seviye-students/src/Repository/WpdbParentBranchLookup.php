<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Students\Contracts\ParentBranchLookupInterface;

/**
 * A separate, minimal adapter rather than composing
 * {@see WpdbStudentParentRepository} + {@see WpdbStudentRepository}: this
 * Contract's only job is "which branches" for one parent, a single joined
 * query - mirrors {@see WpdbStudentGuardianCheck}.
 */
final class WpdbParentBranchLookup implements ParentBranchLookupInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function branchIdsForParent(int $parentUserId): array
    {
        $studentParentsTable = $this->connection->table('student_parents');
        $studentsTable = $this->connection->table('students');

        $sql = $this->connection->prepare(
            'SELECT DISTINCT s.branch_id FROM ' . $studentParentsTable . ' sp '
                . 'INNER JOIN ' . $studentsTable . ' s ON s.id = sp.student_id '
                . 'WHERE sp.parent_user_id = %d',
            [$parentUserId]
        );

        return array_map(
            static fn (array $row): int => (int) $row['branch_id'],
            $this->connection->getResults($sql)
        );
    }
}
