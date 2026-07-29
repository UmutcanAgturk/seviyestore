<?php

declare(strict_types=1);

namespace Seviye\Core\Database;

/**
 * $wpdb-backed adapter implementing {@see ConnectionInterface}.
 */
final class WpdbConnection implements ConnectionInterface
{
    private readonly \wpdb $wpdb;

    public function __construct(?\wpdb $connection = null)
    {
        if ($connection === null) {
            global $wpdb;
            $connection = $wpdb;
        }

        $this->wpdb = $connection;
    }

    public function table(string $suffix): string
    {
        return $this->wpdb->prefix . 'scp_' . ltrim($suffix, '_');
    }

    public function charsetCollate(): string
    {
        return $this->wpdb->get_charset_collate();
    }

    public function dbDelta(string $sql): array
    {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        return dbDelta($sql);
    }

    public function insert(string $table, array $data): bool
    {
        return $this->wpdb->insert($table, $data) !== false;
    }

    public function lastInsertId(): int
    {
        return (int) $this->wpdb->insert_id;
    }

    public function query(string $sql): bool
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is built internally by migrations/the runner, never from request input.
        return $this->wpdb->query($sql) !== false;
    }

    public function getResults(string $sql): array
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is built internally by migrations/the runner, never from request input.
        return (array) $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function prepare(string $sql, array $args): string
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- this method IS the prepare() wrapper; callers pass its result to query()/getResults().
        return $this->wpdb->prepare($sql, $args);
    }
}
