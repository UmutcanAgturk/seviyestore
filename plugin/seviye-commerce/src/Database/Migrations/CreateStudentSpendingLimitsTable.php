<?php

declare(strict_types=1);

namespace Seviye\Commerce\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_student_spending_limits - "kurumun bir öğrenci için dönemlik/
 * aylık harcama üst sınırı koyabilmesi". One row per student (UNIQUE
 * student_id): setting a new limit for a student overwrites the previous
 * one rather than accumulating history, mirroring
 * scp_product_branches' one-row-per-key shape.
 *
 * ABSENCE of a row means "no limit" (opt-in, unlike scp_product_branches'
 * opt-out visibility model) - a student with no row may spend without
 * restriction. See
 * {@see \Seviye\Commerce\Repository\WpdbSpendingLimitRepository::find()}.
 *
 * student_id gets ON DELETE CASCADE (mirrors scp_order_line_items,
 * scp_price_rules): a limit scoped to a deleted student is meaningless.
 */
final class CreateStudentSpendingLimitsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_08_01_000001';
    }

    public function description(): string
    {
        return 'Creates the scp_student_spending_limits table with a FK to scp_students.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('student_spending_limits');
        $studentsTable = $connection->table('students');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id BIGINT UNSIGNED NOT NULL,
            period VARCHAR(20) NOT NULL,
            limit_amount DECIMAL(10,2) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY student_id (student_id)
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
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('student_spending_limits'));
    }
}
