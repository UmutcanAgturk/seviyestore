<?php

declare(strict_types=1);

namespace Seviye\Api\Repository;

use RuntimeException;
use Seviye\Api\Domain\ApiKey;
use Seviye\Core\Database\ConnectionInterface;

final class WpdbApiKeyRepository implements ApiKeyRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(int $userId, string $label, string $keyPrefix, string $keyHash): ApiKey
    {
        $this->connection->insert($this->connection->table('api_keys'), [
            'user_id' => $userId,
            'label' => $label,
            'key_prefix' => $keyPrefix,
            'key_hash' => $keyHash,
            'created_at' => $this->now(),
        ]);

        $table = $this->connection->table('api_keys');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            [$this->connection->lastInsertId()]
        );
        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            throw new RuntimeException('API key could not be read back after insert.');
        }

        return $this->hydrate($rows[0]);
    }

    public function findByHash(string $keyHash): ?ApiKey
    {
        $table = $this->connection->table('api_keys');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE key_hash = %s LIMIT 1",
            [$keyHash]
        );

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function touchLastUsed(int $id): void
    {
        $table = $this->connection->table('api_keys');
        $this->connection->query($this->connection->prepare(
            "UPDATE {$table} SET last_used_at = %s WHERE id = %d",
            [$this->now(), $id]
        ));
    }

    public function revoke(int $id): void
    {
        $table = $this->connection->table('api_keys');
        $this->connection->query($this->connection->prepare(
            "UPDATE {$table} SET revoked_at = %s WHERE id = %d AND revoked_at IS NULL",
            [$this->now(), $id]
        ));
    }

    public function all(): array
    {
        $table = $this->connection->table('api_keys');
        $sql = 'SELECT * FROM ' . $table . ' ORDER BY id DESC';

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ApiKey
    {
        return new ApiKey(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['label'],
            (string) $row['key_prefix'],
            (string) $row['key_hash'],
            (string) $row['created_at'],
            $row['last_used_at'] !== null ? (string) $row['last_used_at'] : null,
            $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
