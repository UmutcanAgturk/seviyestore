<?php

declare(strict_types=1);

namespace Seviye\Core\Settings;

use Seviye\Core\Database\ConnectionInterface;

final class WpdbSettingsRepository implements SettingsRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function get(string $key): ?string
    {
        $table = $this->connection->table('settings');
        $sql = $this->connection->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s LIMIT 1",
            [$key]
        );

        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            return null;
        }

        return $rows[0]['setting_value'] !== null ? (string) $rows[0]['setting_value'] : null;
    }

    /**
     * A single UPSERT (rather than a SELECT-then-INSERT-or-UPDATE) relies on
     * scp_settings' own UNIQUE KEY (setting_key) to make this atomic and
     * race-free under concurrent writers.
     */
    public function set(string $key, string $value): void
    {
        $table = $this->connection->table('settings');
        $sql = $this->connection->prepare(
            "INSERT INTO {$table} (setting_key, setting_value, autoload, updated_at) VALUES (%s, %s, 1, %s)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)",
            [$key, $value, $this->now()]
        );

        $this->connection->query($sql);
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
