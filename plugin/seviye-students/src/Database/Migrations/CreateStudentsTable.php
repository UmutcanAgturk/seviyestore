<?php

declare(strict_types=1);

namespace Seviye\Students\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_students, with a real FK to scp_branches. Deliberately no
 * ON DELETE CASCADE here (unlike scp_branch_users): silently deleting every
 * student when a branch is removed is the kind of high-blast-radius,
 * hard-to-reverse data loss this platform avoids by design (see
 * plugin/seviye-core/uninstall.php for the same principle). InnoDB's
 * default (RESTRICT) applies, so a branch with students cannot be deleted
 * until they are reassigned or removed explicitly.
 */
final class CreateStudentsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_28_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_students table with a FK to scp_branches.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('students');
        $branchesTable = $connection->table('branches');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id BIGINT UNSIGNED NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            education_year VARCHAR(9) NOT NULL,
            class_name VARCHAR(50) NOT NULL,
            tc_no CHAR(11) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY branch_id (branch_id),
            KEY education_year (education_year)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        ForeignKeyInstaller::ensure(
            $connection,
            $table,
            $table . '_branch_id_fk',
            "FOREIGN KEY (branch_id) REFERENCES {$branchesTable} (id)"
        );
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('students'));
    }
}
