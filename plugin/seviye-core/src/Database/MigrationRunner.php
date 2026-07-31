<?php

declare(strict_types=1);

namespace Seviye\Core\Database;

use Psr\Log\LoggerInterface;

/**
 * Executes every registered migration, in version order, on every call -
 * safe because every migration's up() in this codebase is a dbDelta() CREATE
 * TABLE statement, and dbDelta() is itself idempotent (diffs against the
 * live schema, only applies what's actually missing). scp_migrations is
 * still kept, but purely as a first-applied audit log, not a gate: dbDelta()
 * never throws on failure, so a version that failed to create anything
 * (a MySQL privilege issue, a transient error, ...) could previously get
 * permanently marked "applied" by a naive run-once tracker, silently
 * leaving its table missing forever after. Always re-invoking up() means a
 * table lost or never actually created self-heals on the very next call
 * instead.
 *
 * Each Seviye module owns its own MigrationRunner usage: it registers its
 * migrations against the shared runner instance (resolved from Core's
 * container) during its own plugin activation, and again on every wp-admin
 * page load via Plugin::boot() (see docs/ARCHITECTURE.md, "Üçüncü kural").
 * Rollback (down()) is defined per-migration but not yet orchestrated here -
 * see docs/ARCHITECTURE.md for the planned CLI-driven rollback workflow.
 *
 * version() strings are only ever meant to be unique WITHIN one module -
 * every module numbers its own migrations independently (e.g. Branches',
 * Core's, Security's, Students' and Parents' first migration all happen to
 * be dated "2026_07_28_000001"), so the internal registry is keyed on
 * version() PLUS the migration's class name, never version() alone. Keying
 * on version() alone (the original implementation) meant every module
 * registering a same-dated migration silently evicted the previous one from
 * the array - only the last-booted module's migration for that date ever
 * actually ran, so e.g. scp_branches or scp_logs could go permanently
 * missing on some installs purely depending on plugin activation order,
 * with no error anywhere (see docs/ARCHITECTURE.md, "Dördüncü kural"). The
 * class name is only a sort tiebreaker for migrations sharing a version
 * string - ksort() still sorts by version() first, so a same-dated
 * migration with a real FK dependency on another (e.g. Students' scp_students
 * on Branches' scp_branches) is not guaranteed relative order beyond
 * alphabetical-by-class-name, which is why ForeignKeyInstaller::ensure()
 * fails soft instead of throwing if its referenced table isn't there yet.
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
        $this->migrations[$migration->version() . '@' . get_class($migration)] = $migration;
    }

    /**
     * Re-applies every registered migration's up() (dbDelta - idempotent,
     * see class docblock) and records first-time applications in
     * scp_migrations for audit purposes.
     *
     * @return list<string> Versions applied for the first time during this call.
     */
    public function run(): array
    {
        $this->ensureMigrationsTableExists();

        $applied = $this->appliedVersions();
        $executed = [];

        $migrations = $this->migrations;
        ksort($migrations);

        foreach ($migrations as $migration) {
            $version = $migration->version();

            $migration->up($this->connection);

            if (in_array($version, $applied, true)) {
                continue;
            }

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
