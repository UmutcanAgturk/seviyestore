<?php

declare(strict_types=1);

namespace Seviye\Security\Token;

use DateTimeImmutable;
use Seviye\Core\Database\ConnectionInterface;

final class WpdbPasswordTokenGateway implements PasswordTokenGatewayInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function store(
        int $userId,
        string $tokenHash,
        PasswordTokenPurpose $purpose,
        DateTimeImmutable $expiresAt
    ): void {
        $this->connection->insert($this->connection->table('password_tokens'), [
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'purpose' => $purpose->value,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function find(string $tokenHash): ?PasswordTokenRecord
    {
        $table = $this->connection->table('password_tokens');
        $sql = $this->connection->prepare(
            "SELECT user_id, purpose, expires_at FROM {$table} WHERE token_hash = %s LIMIT 1",
            [$tokenHash]
        );

        $rows = $this->connection->getResults($sql);

        if (!isset($rows[0])) {
            return null;
        }

        $purpose = PasswordTokenPurpose::from((string) $rows[0]['purpose']);
        $expiresAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $rows[0]['expires_at']);

        if ($expiresAt === false) {
            return null;
        }

        return new PasswordTokenRecord((int) $rows[0]['user_id'], $purpose, $expiresAt);
    }

    public function consume(string $tokenHash): void
    {
        $table = $this->connection->table('password_tokens');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE token_hash = %s", [$tokenHash]);

        $this->connection->query($sql);
    }
}
