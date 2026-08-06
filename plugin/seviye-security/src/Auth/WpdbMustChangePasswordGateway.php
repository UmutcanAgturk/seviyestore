<?php

declare(strict_types=1);

namespace Seviye\Security\Auth;

use Seviye\Core\Database\ConnectionInterface;

final class WpdbMustChangePasswordGateway implements MustChangePasswordGatewayInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function flag(int $userId): void
    {
        // REPLACE INTO, not insert(): flagging an already-flagged user
        // (e.g. an admin resets a password twice before the user ever logs
        // in) must not throw on the PRIMARY KEY collision - it's a no-op
        // either way, the row already says "must change".
        $table = $this->connection->table('must_change_password_flags');
        $sql = $this->connection->prepare(
            "REPLACE INTO {$table} (user_id, flagged_at) VALUES (%d, %s)",
            [$userId, function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s')]
        );

        $this->connection->query($sql);
    }

    public function clear(int $userId): void
    {
        $table = $this->connection->table('must_change_password_flags');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE user_id = %d", [$userId]);
        $this->connection->query($sql);
    }

    public function isFlagged(int $userId): bool
    {
        $table = $this->connection->table('must_change_password_flags');
        $sql = $this->connection->prepare(
            "SELECT user_id FROM {$table} WHERE user_id = %d LIMIT 1",
            [$userId]
        );

        return $this->connection->getResults($sql) !== [];
    }
}
