<?php

declare(strict_types=1);

namespace Seviye\Core\Database;

/**
 * Port abstracting the underlying database driver (WordPress' $wpdb in
 * production, an in-memory fake in tests) so schema and query logic stays
 * testable without a running WordPress + MySQL stack.
 */
interface ConnectionInterface
{
    /**
     * Resolves a bare table suffix (e.g. "logs") to its fully prefixed,
     * ready-to-query name (e.g. "wp_scp_logs"), centralising the "scp_"
     * table naming convention used across the whole platform.
     */
    public function table(string $suffix): string;

    public function charsetCollate(): string;

    /**
     * Runs a CREATE/ALTER TABLE statement through WordPress' dbDelta(), which
     * diffs the statement against the live schema and applies only what
     * changed - safe to call on every request.
     *
     * @return array<int, string>
     */
    public function dbDelta(string $sql): array;

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): bool;

    public function query(string $sql): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public function getResults(string $sql): array;

    /**
     * Safely interpolates values into a SQL string (via $wpdb->prepare() in
     * production). Any query built from request input - not just internal,
     * hard-coded migration DDL - MUST be built through this method before
     * being passed to {@see query()} or {@see getResults()}.
     *
     * @param array<int|string, mixed> $args
     */
    public function prepare(string $sql, array $args): string;
}
