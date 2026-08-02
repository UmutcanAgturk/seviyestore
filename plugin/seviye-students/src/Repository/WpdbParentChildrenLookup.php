<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Students\Contracts\ParentChildrenLookupInterface;
use Seviye\Students\Contracts\StudentSummary;

/**
 * A separate, minimal adapter rather than composing
 * {@see WpdbStudentParentRepository}'s studentIdsForParent() with N calls
 * into {@see WpdbStudentLookup} - a single joined query instead of an N+1,
 * mirrors {@see WpdbBranchParentLookup}'s reasoning exactly.
 */
final class WpdbParentChildrenLookup implements ParentChildrenLookupInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function childrenOf(int $parentUserId): array
    {
        $studentParentsTable = $this->connection->table('student_parents');
        $studentsTable = $this->connection->table('students');

        $sql = $this->connection->prepare(
            'SELECT s.id, s.branch_id, s.first_name, s.last_name FROM ' . $studentsTable . ' s '
                . 'INNER JOIN ' . $studentParentsTable . ' sp ON sp.student_id = s.id '
                . 'WHERE sp.parent_user_id = %d ORDER BY s.first_name ASC, s.last_name ASC',
            [$parentUserId]
        );

        return array_map(
            static fn (array $row): StudentSummary => new StudentSummary(
                (int) $row['id'],
                (int) $row['branch_id'],
                (string) $row['first_name'],
                (string) $row['last_name']
            ),
            $this->connection->getResults($sql)
        );
    }
}
