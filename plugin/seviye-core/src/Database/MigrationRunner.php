<?php

declare(strict_types=1);

namespace Seviye\Core\Database;

use Psr\Log\LoggerInterface;

/**
 * Tracks and executes registered migrations exactly once, in version order.
 *
 * Each Seviye module owns its own MigrationRunner usage: it registers its
 * migrations against the shared runner instance (resolved from Core's
 * container) during its own plugin activation. Rollback (down()) is defined
 * per-migration but not yet orchestrated here - see docs/ARCHITECTURE.md for
 * the planned CLI-driven rollback workflow.
 */
final class MigrationRunner
{
    /** @var array<string, MigrationInterface> */
    private array $migrations = [];

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function register(MigrationInterface $migration): void
    {
        $this->migrations[$migration->version()] = $migration;
    }

    /**
     * Executes every registered migration that has not yet run, in version order.
     *
     * @return list<string> Versions executed during this call.
     */
    public function run(): array
    {
        $this->ensureMigrationsTableExists();

        $applied = $this->appliedVersions();
        $executed = [];

        $migrations = $this->migrations;
        ksort($migrations);

        foreach ($migrations as $version => $migration) {
            if (in_array($version, $applied, true)) {
                continue;
            }

            $migration->up($this->connection);
            $this->recordMigration($migration);
            $executed[] = $version;

            $this->logger->info('Migration executed.', [
                'channel' => 'core.migrations',
                'version' => $version,
                'description' => $migration->description(),
            ]);
        }

        return $executed;
    }

    private function ensureMigrationsTableExists(): void
    {
        $table = $this->connection->table('migrations');
        $charsetCollate = $this->connection->charsetCollate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            version VARCHAR(32) NOT NULL,
            description VARCHAR(255) NOT NULL,
            executed_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY version (version)
        ) {$charsetCollate};";

        $this->connection->dbDelta($sql);
    }

    /**
     * @return list<string>
     */
    private function appliedVersions(): array
    {
        $table = $this->connection->table('migrations');
        $rows = $this->connection->getResults("SELECT version FROM {$table}");

        return array_map(static fn (array $row): string => (string) $row['version'], $rows);
    }

    private function recordMigration(MigrationInterface $migration): void
    {
        $this->connection->insert($this->connection->table('migrations'), [
            'version' => $migration->version(),
            'description' => $migration->description(),
            'executed_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ]);
    }
}
