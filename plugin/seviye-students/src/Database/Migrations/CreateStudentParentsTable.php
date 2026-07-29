<?php

declare(strict_types=1);

namespace Seviye\Students\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_student_parents: the many-to-many link between a student and
 * their guardian(s) - "bir öğrenci bir veya daha fazla veliye bağlı
 * olabilir", "bir veli birden fazla öğrenciyi görebilir".
 *
 * parent_user_id is a WordPress user ID (a "Veli" logs in themselves) but,
 * like scp_user_identities in Seviye Security, deliberately carries no FK
 * to wp_users. student_id DOES get a real FK to scp_students, with
 * ON DELETE CASCADE: unlike deleting a whole branch's worth of students,
 * removing one student's own link rows when that student is deleted is
 * expected cleanup, not surprising data loss.
 */
final class CreateStudentParentsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_28_000002';
    }

    public function description(): string
    {
        return 'Creates the scp_student_parents table linking students to guardian WordPress users.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('student_parents');
        $studentsTable = $connection->table('students');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            parent_user_id BIGINT UNSIGNED NOT NULL,
            relationship_type VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY student_parent (student_id, parent_user_id),
            KEY parent_user_id (parent_user_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_student_id_fk',
            "FOREIGN KEY (student_id) REFERENCES {$studentsTable} (id) ON DELETE CASCADE"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('student_parents'));
    }
}
