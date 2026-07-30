<?php

declare(strict_types=1);

namespace Seviye\Security\TwoFactor;

use Seviye\Core\Database\ConnectionInterface;

final class WpdbTwoFactorGateway implements TwoFactorGatewayInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function find(int $userId): ?TwoFactorSecret
    {
        $table = $this->connection->table('two_factor_secrets');
        $sql = $this->connection->prepare(
            "SELECT secret_encrypted, confirmed_at FROM {$table} WHERE user_id = %d LIMIT 1",
            [$userId]
        );

        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            return null;
        }

        return new TwoFactorSecret(
            $userId,
            (string) $rows[0]['secret_encrypted'],
            $rows[0]['confirmed_at'] !== null
        );
    }

    public function store(int $userId, string $encryptedSecret): void
    {
        $table = $this->connection->table('two_factor_secrets');
        $sql = $this->connection->prepare(
            "INSERT INTO {$table} (user_id, secret_encrypted, confirmed_at, created_at) VALUES (%d, %s, NULL, %s)
             ON DUPLICATE KEY UPDATE secret_encrypted = VALUES(secret_encrypted), confirmed_at = NULL",
            [$userId, $encryptedSecret, $this->now()]
        );

        $this->connection->query($sql);
    }

    public function confirm(int $userId): void
    {
        $table = $this->connection->table('two_factor_secrets');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET confirmed_at = %s WHERE user_id = %d",
            [$this->now(), $userId]
        );

        $this->connection->query($sql);
    }

    public function delete(int $userId): void
    {
        $table = $this->connection->table('two_factor_secrets');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE user_id = %d", [$userId]);

        $this->connection->query($sql);
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
