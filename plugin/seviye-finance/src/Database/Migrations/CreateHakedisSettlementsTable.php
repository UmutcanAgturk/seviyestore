<?php

declare(strict_types=1);

namespace Seviye\Finance\Database\Migrations;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Core\Database\ForeignKeyInstaller;
use Seviye\Core\Database\MigrationInterface;

/**
 * Creates scp_hakedis_settlements: an immutable, append-only "tahsilat"
 * (payout) ledger, separate from scp_hakedis_entries. A branch's balance is
 * accrued (SUM of hakedis_entries.amount) minus settled (SUM of this
 * table's amount) - two additive ledgers, never one table with an editable
 * "paid" flag, so neither ever needs an UPDATE (see Domain\HakedisEntry and
 * Domain\HakedisSettlement for the immutability reasoning this mirrors).
 *
 * Real FK to scp_branches, RESTRICT (the default) - same reasoning as
 * scp_hakedis_entries: this is financial history, losing it because a
 * branch was later deleted would be irreversible data loss.
 *
 * recorded_by is intentionally NOT a foreign key to wp_users - the same
 * reasoning as Security's scp_user_identities.user_id (see
 * CreateUserIdentitiesTable): WordPress core does not guarantee a stable
 * storage engine/charset for its own tables, so plugins should not
 * constrain against them at the database level. The REST layer only ever
 * writes get_current_user_id() here, a value WordPress has already
 * authenticated.
 *
 * No UNIQUE constraint (unlike scp_hakedis_entries' idempotency guard):
 * a settlement is a manual HQ action, not a reaction to an idempotency-
 * sensitive event, and two genuinely separate payouts of the same amount
 * on the same day are a real, unremarkable possibility.
 */
final class CreateHakedisSettlementsTable implements MigrationInterface
{
    public function version(): string
    {
        return '2026_07_29_000002';
    }

    public function description(): string
    {
        return 'Creates the scp_hakedis_settlements table with a FK to scp_branches.';
    }

    public function up(ConnectionInterface $connection): void
    {
        $table = $connection->table('hakedis_settlements');
        $branchesTable = $connection->table('branches');
        $charsetCollate = $connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(20) NOT NULL,
            note TEXT NULL,
            recorded_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY branch_id (branch_id)
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
        $connection->query('DROP TABLE IF EXISTS ' . $connection->table('hakedis_settlements'));
    }
}
