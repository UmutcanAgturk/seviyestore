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

        $inserted = $this->connection->insert($table, [
            'student_id' => $studentId,
            'parent_user_id' => $parentUserId,
            'relationship_type' => $relationship->value,
            'created_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ]);

        if ($inserted) {
            return;
        }

        // Every caller (StudentsRestController's manual link + auto-link-on-
        // create paths) used to assume this always succeeds and reported
        // success regardless - masking a real INSERT failure (a missing
        // scp_student_parents table, a stale duplicate row, ...) as a
        // silent no-op with no error anywhere. Same fix already applied to
        // WpdbIdentityGateway::link() and WpdbBranchRepository::create()/
        // update() this session - throwing here with the real $wpdb error
        // lets callers surface it instead of showing a false "Kaydedildi".
        global $wpdb;
        $dbError = isset($wpdb) && $wpdb->last_error !== '' ? $wpdb->last_error : 'bilinmeyen veritabanı hatası';
        $message = sprintf(
            'Öğrenci #%d - veli #%d bağlantısı kaydedilemedi: %s',
            $studentId,
            $parentUserId,
            $dbError
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
        throw new \RuntimeException($message);
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
