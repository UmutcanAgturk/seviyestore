<?php

declare(strict_types=1);

namespace Seviye\Security\Identity;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Security\Auth\TcNumber;

final class WpdbIdentityGateway implements IdentityGatewayInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function findUserIdByTcNumber(TcNumber $tcNumber): ?int
    {
        $table = $this->connection->table('user_identities');
        $sql = $this->connection->prepare(
            "SELECT user_id FROM {$table} WHERE tc_no = %s LIMIT 1",
            [$tcNumber->value()]
        );

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]['user_id']) ? (int) $rows[0]['user_id'] : null;
    }

    public function tcNumberExists(TcNumber $tcNumber): bool
    {
        return $this->findUserIdByTcNumber($tcNumber) !== null;
    }

    public function link(TcNumber $tcNumber, int $userId): void
    {
        $this->connection->insert($this->connection->table('user_identities'), [
            'user_id' => $userId,
            'tc_no' => $tcNumber->value(),
            'created_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ]);
    }
}
