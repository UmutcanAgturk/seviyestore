<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Students\Contracts\StudentGuardianCheckInterface;

/**
 * A separate, minimal adapter rather than reusing
 * {@see WpdbStudentParentRepository}: that class exposes link/unlink and
 * full id lists for Students' own REST needs, while this Contract's only
 * job is a single yes/no membership check for other modules - mirrors
 * Seviye\Branches\Repository\WpdbBranchLookup.
 */
final class WpdbStudentGuardianCheck implements StudentGuardianCheckInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function isGuardianOf(int $parentUserId, int $studentId): bool
    {
        $table = $this->connection->table('student_parents');
        $sql = $this->connection->prepare(
            'SELECT id FROM ' . $table . ' WHERE parent_user_id = %d AND student_id = %d LIMIT 1',
            [$parentUserId, $studentId]
        );

        return $this->connection->getResults($sql) !== [];
    }
}
