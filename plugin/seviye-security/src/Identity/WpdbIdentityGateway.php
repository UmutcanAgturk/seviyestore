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

    public function findTcNumberByUserId(int $userId): ?TcNumber
    {
        $table = $this->connection->table('user_identities');
        $sql = $this->connection->prepare(
            "SELECT tc_no FROM {$table} WHERE user_id = %d LIMIT 1",
            [$userId]
        );

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]['tc_no']) ? TcNumber::fromString((string) $rows[0]['tc_no']) : null;
    }

    public function tcNumberExists(TcNumber $tcNumber): bool
    {
        return $this->findUserIdByTcNumber($tcNumber) !== null;
    }

    public function link(TcNumber $tcNumber, int $userId): void
    {
        $inserted = $this->connection->insert($this->connection->table('user_identities'), [
            'user_id' => $userId,
            'tc_no' => $tcNumber->value(),
            'created_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ]);

        if ($inserted) {
            return;
        }

        // Every wp-admin caller (UserListPage, UserAuthorizationAdminPage)
        // used to assume this always succeeds and unconditionally showed
        // "Kaydedildi" - masking a real INSERT failure (a stale row from an
        // earlier attempt violating the UNIQUE KEY on user_id/tc_no, a
        // missing table, ...) as a false success. Throwing here with the
        // real $wpdb error lets those callers surface it instead.
        global $wpdb;
        $dbError = isset($wpdb) && $wpdb->last_error !== '' ? $wpdb->last_error : 'bilinmeyen veritabanı hatası';
        $message = sprintf('T.C. Kimlik No eşleşmesi kaydedilemedi (user #%d): %s', $userId, $dbError);

        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
        throw new \RuntimeException($message);
    }

    public function unlink(int $userId): void
    {
        $table = $this->connection->table('user_identities');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE user_id = %d", [$userId]);
        $this->connection->query($sql);
    }
}
