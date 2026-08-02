<?php

declare(strict_types=1);

namespace Seviye\Security\Privacy;

use Seviye\Core\Database\ConnectionInterface;

final class WpdbPrivacyRequestGateway implements PrivacyRequestGatewayInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(int $userId, PrivacyRequestType $type, PrivacyRequestStatus $status, ?string $note): int
    {
        $now = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');

        $this->connection->insert($this->connection->table('privacy_requests'), [
            'user_id' => $userId,
            'type' => $type->value,
            'status' => $status->value,
            'note' => $note,
            'requested_at' => $now,
            'resolved_at' => $status === PrivacyRequestStatus::COMPLETED ? $now : null,
        ]);

        return $this->connection->lastInsertId();
    }

    public function find(int $id): ?PrivacyRequest
    {
        $table = $this->connection->table('privacy_requests');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);
        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function forUser(int $userId): array
    {
        $table = $this->connection->table('privacy_requests');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY requested_at DESC",
            [$userId]
        );

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    public function pending(): array
    {
        $table = $this->connection->table('privacy_requests');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE status = %s ORDER BY requested_at ASC",
            [PrivacyRequestStatus::PENDING->value]
        );

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    public function resolve(int $id, PrivacyRequestStatus $status, ?string $resolutionNote, int $resolvedBy): void
    {
        $table = $this->connection->table('privacy_requests');
        $now = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');

        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, resolution_note = %s, resolved_at = %s, resolved_by = %d WHERE id = %d",
            [$status->value, $resolutionNote, $now, $resolvedBy, $id]
        );

        $this->connection->query($sql);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PrivacyRequest
    {
        return new PrivacyRequest(
            (int) $row['id'],
            (int) $row['user_id'],
            PrivacyRequestType::from((string) $row['type']),
            PrivacyRequestStatus::from((string) $row['status']),
            $row['note'] !== null ? (string) $row['note'] : null,
            $row['resolution_note'] !== null ? (string) $row['resolution_note'] : null,
            (string) $row['requested_at'],
            $row['resolved_at'] !== null ? (string) $row['resolved_at'] : null,
            $row['resolved_by'] !== null ? (int) $row['resolved_by'] : null
        );
    }
}
