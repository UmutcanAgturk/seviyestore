<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Students\Contracts\ParentClassLookupInterface;

/**
 * A separate, minimal adapter rather than composing
 * {@see WpdbStudentParentRepository} + {@see WpdbStudentRepository}: this
 * Contract's only job is "which class names" for one parent, a single
 * joined query - mirrors {@see WpdbParentBranchLookup} exactly, one field
 * over.
 */
final class WpdbParentClassLookup implements ParentClassLookupInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function classNamesForParent(int $parentUserId): array
    {
        $studentParentsTable = $this->connection->table('student_parents');
        $studentsTable = $this->connection->table('students');

        $sql = $this->connection->prepare(
            'SELECT DISTINCT s.class_name FROM ' . $studentParentsTable . ' sp '
                . 'INNER JOIN ' . $studentsTable . ' s ON s.id = sp.student_id '
                . 'WHERE sp.parent_user_id = %d',
            [$parentUserId]
        );

        return array_map(
            static fn (array $row): string => (string) $row['class_name'],
            $this->connection->getResults($sql)
        );
    }
}
