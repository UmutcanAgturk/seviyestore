<?php

declare(strict_types=1);

namespace Seviye\Core\Database;

/**
 * Adds an InnoDB FOREIGN KEY constraint to a table already created via
 * {@see ConnectionInterface::dbDelta()}, idempotently.
 *
 * dbDelta() does not reliably parse FOREIGN KEY clauses inside a CREATE
 * TABLE statement (a known WordPress limitation), so any migration that
 * needs a real FK between two of the platform's own tables creates the
 * table without one, then calls this helper.
 */
final class ForeignKeyInstaller
{
    public static function ensure(
        ConnectionInterface $connection,
        string $table,
        string $constraintName,
        string $definition
    ): void {
        $sql = $connection->prepare(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS '
                . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s',
            [$table, $constraintName]
        );

        if ($connection->getResults($sql) !== []) {
            return;
        }

        $connection->query("ALTER TABLE {$table} ADD CONSTRAINT {$constraintName} {$definition}");
    }
}
