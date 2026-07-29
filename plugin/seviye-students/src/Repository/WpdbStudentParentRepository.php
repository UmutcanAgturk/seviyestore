<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Students\Domain\ParentRelationship;

final class WpdbStudentParentRepository implements StudentParentRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function link(int $studentId, int $parentUserId, ParentRelationship $relationship): void
    {
        $table = $this->connection->table('student_parents');

        if ($this->isLinked($studentId, $parentUserId)) {
            $sql = $this->connection->prepare(
                "UPDATE {$table} SET relationship_type = %s WHERE student_id = %d AND parent_user_id = %d",
                [$relationship->value, $studentId, $parentUserId]
            );
            $this->connection->query($sql);

            return;
        }

        $this->connection->insert($table, [
            'student_id' => $studentId,
            'parent_user_id' => $parentUserId,
            'relationship_type' => $relationship->value,
            'created_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function unlink(int $studentId, int $parentUserId): void
    {
        $table = $this->connection->table('student_parents');
        $sql = $this->connection->prepare(
            "DELETE FROM {$table} WHERE student_id = %d AND parent_user_id = %d",
            [$studentId, $parentUserId]
        );

        $this->connection->query($sql);
    }

    public function parentUserIdsForStudent(int $studentId): array
    {
        $table = $this->connection->table('student_parents');
        $sql = $this->connection->prepare("SELECT parent_user_id FROM {$table} WHERE student_id = %d", [$studentId]);

        return array_map(
            static fn (array $row): int => (int) $row['parent_user_id'],
            $this->connection->getResults($sql)
        );
    }

    public function studentIdsForParent(int $parentUserId): array
    {
        $table = $this->connection->table('student_parents');
        $sql = $this->connection->prepare(
            "SELECT student_id FROM {$table} WHERE parent_user_id = %d",
            [$parentUserId]
        );

        return array_map(
            static fn (array $row): int => (int) $row['student_id'],
            $this->connection->getResults($sql)
        );
    }

    private function isLinked(int $studentId, int $parentUserId): bool
    {
        $table = $this->connection->table('student_parents');
        $sql = $this->connection->prepare(
            "SELECT id FROM {$table} WHERE student_id = %d AND parent_user_id = %d LIMIT 1",
            [$studentId, $parentUserId]
        );

        return $this->connection->getResults($sql) !== [];
    }
}
