<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Students\Contracts\BranchParentLookupInterface;

/**
 * A separate, minimal adapter rather than composing
 * {@see WpdbStudentParentRepository} + {@see WpdbStudentRepository}: this
 * Contract's only job is "which veli user ids" for a branch (or every
 * branch), a single joined query - mirrors
 * {@see WpdbParentBranchLookup}, its reverse-direction counterpart.
 */
final class WpdbBranchParentLookup implements BranchParentLookupInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function parentUserIdsForBranch(int $branchId): array
    {
        $studentParentsTable = $this->connection->table('student_parents');
        $studentsTable = $this->connection->table('students');

        $sql = $this->connection->prepare(
            'SELECT DISTINCT sp.parent_user_id FROM ' . $studentParentsTable . ' sp '
                . 'INNER JOIN ' . $studentsTable . ' s ON s.id = sp.student_id '
                . 'WHERE s.branch_id = %d',
            [$branchId]
        );

        return $this->parentUserIds($sql);
    }

    public function allParentUserIds(): array
    {
        $studentParentsTable = $this->connection->table('student_parents');

        return $this->parentUserIds('SELECT DISTINCT parent_user_id FROM ' . $studentParentsTable);
    }

    /**
     * @return list<int>
     */
    private function parentUserIds(string $sql): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['parent_user_id'],
            $this->connection->getResults($sql)
        );
    }
}
