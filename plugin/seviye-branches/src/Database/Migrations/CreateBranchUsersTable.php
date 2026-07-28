<?php

declare(strict_types=1);

namespace Seviye\Branches\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_branch_users: the "Yetkililer" (staff) assigned to a branch.
 * A staff user belongs to at most one branch (unique user_id); HQ-level
 * roles (Genel Merkez, Bölge Müdürü) simply have no row here.
 *
 * Both tables are our own (not WordPress core), so - unlike the wp_users
 * reference in Seviye Security - a real InnoDB FOREIGN KEY is used here.
 * dbDelta() does not reliably parse FOREIGN KEY clauses, so the constraint
 * is added in a separate, idempotency-guarded step after it.
 */
final class CreateBranchUsersTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_28_000002';
    }

    public function description(): string
    {
        return 'Creates the scp_branch_users table (branch staff membership) with a FK to scp_branches.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('branch_users');
        $branchesTable = $connection->table('branches');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            KEY branch_id (branch_id)
        ) {$charsetCollate};";

        $connection->dbDelta($sql);

        $this->ensureForeignKey($connection, $table, $branchesTable);
    }

    public function down(ConnectionInterface $connection): void
    {
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('branch_users'));
    }

    private function ensureForeignKey(ConnectionInterface $connection, string $table, string $branchesTable): void
    {
        $constraintName = $table . '_branch_id_fk';

        $sql = $connection->prepare(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS '
                . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s',
            [$table, $constraintName]
        );

        if ($connection->getResults($sql) !== []) {
            return;
        }

        $connection->query(
            "ALTER TABLE {$table} ADD CONSTRAINT {$constraintName} "
                . "FOREIGN KEY (branch_id) REFERENCES {$branchesTable} (id) ON DELETE CASCADE"
        );
    }
}
