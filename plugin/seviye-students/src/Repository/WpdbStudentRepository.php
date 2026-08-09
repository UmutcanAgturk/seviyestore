<?php

declare(strict_types=1);

namespace Seviye\Students\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Students\Domain\EducationYear;
use Seviye\Students\Domain\Student;
use Seviye\Students\Domain\StudentStatus;

final class WpdbStudentRepository implements StudentRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(
        int $branchId,
        string $firstName,
        string $lastName,
        EducationYear $educationYear,
        string $className,
        ?string $tcNo = null
    ): Student {
        $now = $this->now();

        $this->connection->insert($this->connection->table('students'), [
            'branch_id' => $branchId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'education_year' => $educationYear->value(),
            'class_name' => $className,
            'tc_no' => $tcNo,
            'status' => StudentStatus::ACTIVE->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $student = $this->find($this->connection->lastInsertId());

        if ($student === null) {
            throw new RuntimeException('Student could not be read back after insert.');
        }

        return $student;
    }

    public function update(
        int $id,
        int $branchId,
        string $firstName,
        string $lastName,
        EducationYear $educationYear,
        string $className,
        StudentStatus $status,
        ?string $tcNo = null
    ): Student {
        $table = $this->connection->table('students');
        $sql = $this->connection->prepare(
            'UPDATE ' . $table . ' SET branch_id = %d, first_name = %s, last_name = %s, '
                . 'education_year = %s, class_name = %s, tc_no = %s, status = %s, updated_at = %s WHERE id = %d',
            [
                $branchId,
                $firstName,
                $lastName,
                $educationYear->value(),
                $className,
                $tcNo,
                $status->value,
                $this->now(),
                $id,
            ]
        );

        $this->connection->query($sql);

        $student = $this->find($id);

        if ($student === null) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new RuntimeException(sprintf('Student #%d could not be read back after update.', $id));
        }

        return $student;
    }

    public function find(int $id): ?Student
    {
        $table = $this->connection->table('students');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    /**
     * scp_student_parents and scp_price_rules both declare
     * `FOREIGN KEY (student_id) ... ON DELETE CASCADE` (see their own
     * migrations), so those rows clean up automatically. Seviye Commerce's
     * scp_order_line_items deliberately does NOT cascade (see
     * CreateOrderLineItemsTable) - a student with purchase history must not
     * be silently deletable, so that specific FK violation is translated
     * into an actionable message instead of a raw MySQL error string.
     */
    public function delete(int $id): void
    {
        $table = $this->connection->table('students');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE id = %d", [$id]);

        if ($this->connection->query($sql)) {
            return;
        }

        $error = $this->lastDbError();

        if (stripos($error, 'foreign key constraint') !== false) {
            throw new RuntimeException(
                'Bu öğrenciye ait sipariş kayıtları olduğu için silinemiyor.'
            );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
        throw new RuntimeException(sprintf('Öğrenci #%d silinemedi: %s', $id, $error));
    }

    public function updatePhoto(int $id, ?int $attachmentId): void
    {
        $table = $this->connection->table('students');

        // %d has no NULL placeholder in $wpdb->prepare() - "kaldır"
        // (remove photo) is expressed as a literal SQL NULL instead of a
        // parameterized 0, which would otherwise round-trip as a
        // (nonsensical) attachment ID 0 rather than "no photo".
        $sql = $attachmentId === null
            ? $this->connection->prepare(
                'UPDATE ' . $table . ' SET photo_attachment_id = NULL, updated_at = %s WHERE id = %d',
                [$this->now(), $id]
            )
            : $this->connection->prepare(
                'UPDATE ' . $table . ' SET photo_attachment_id = %d, updated_at = %s WHERE id = %d',
                [$attachmentId, $this->now(), $id]
            );

        $this->connection->query($sql);
    }

    public function all(): array
    {
        $table = $this->connection->table('students');
        $rows = $this->connection->getResults("SELECT * FROM {$table} ORDER BY last_name ASC, first_name ASC");

        return array_map($this->hydrate(...), $rows);
    }

    public function findByBranch(int $branchId): array
    {
        $table = $this->connection->table('students');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE branch_id = %d ORDER BY last_name ASC, first_name ASC",
            [$branchId]
        );

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Student
    {
        return new Student(
            (int) $row['id'],
            (int) $row['branch_id'],
            (string) $row['first_name'],
            (string) $row['last_name'],
            EducationYear::fromString((string) $row['education_year']),
            (string) $row['class_name'],
            StudentStatus::from((string) $row['status']),
            isset($row['tc_no']) && $row['tc_no'] !== '' ? (string) $row['tc_no'] : null,
            isset($row['photo_attachment_id']) && $row['photo_attachment_id'] !== null
                ? (int) $row['photo_attachment_id']
                : null
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }

    private function lastDbError(): string
    {
        global $wpdb;

        return isset($wpdb) && $wpdb->last_error !== '' ? $wpdb->last_error : 'bilinmeyen veritabanı hatası';
    }
}
