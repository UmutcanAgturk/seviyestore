<?php

declare(strict_types=1);

namespace Seviye\Destek\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Destek\Domain\SupportMessage;
use Seviye\Destek\Domain\SupportTicket;
use Seviye\Destek\Domain\TicketStatus;

final class WpdbSupportTicketRepository implements SupportTicketRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(?int $branchId, int $createdByUserId, string $subject, string $message): SupportTicket
    {
        $now = $this->now();

        $this->connection->insert($this->connection->table('support_tickets'), [
            'branch_id' => $branchId,
            'created_by' => $createdByUserId,
            'subject' => $subject,
            'status' => TicketStatus::OPEN->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $ticketId = $this->connection->lastInsertId();

        $this->connection->insert($this->connection->table('support_messages'), [
            'ticket_id' => $ticketId,
            'author_user_id' => $createdByUserId,
            'is_staff' => 0,
            'message' => $message,
            'created_at' => $now,
        ]);

        $ticket = $this->find($ticketId);

        if ($ticket === null) {
            throw new RuntimeException('Destek talebi eklendikten sonra okunamadı.');
        }

        return $ticket;
    }

    public function find(int $id): ?SupportTicket
    {
        $row = $this->findHeaderRow($id);

        return $row !== null ? $this->hydrateHeader($row, $this->messagesFor($id)) : null;
    }

    public function allForUser(int $userId): array
    {
        $table = $this->connection->table('support_tickets');
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE created_by = %d ORDER BY updated_at DESC, id DESC",
            [$userId]
        );

        return $this->hydrateRows($this->connection->getResults($sql));
    }

    public function allForBranch(?int $branchId): array
    {
        $table = $this->connection->table('support_tickets');

        if ($branchId === null) {
            $sql = "SELECT * FROM {$table} ORDER BY updated_at DESC, id DESC";

            return $this->hydrateRows($this->connection->getResults($sql));
        }

        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE branch_id = %d ORDER BY updated_at DESC, id DESC",
            [$branchId]
        );

        return $this->hydrateRows($this->connection->getResults($sql));
    }

    public function addMessage(int $ticketId, int $authorUserId, bool $isStaff, string $message): SupportMessage
    {
        $now = $this->now();

        $this->connection->insert($this->connection->table('support_messages'), [
            'ticket_id' => $ticketId,
            'author_user_id' => $authorUserId,
            'is_staff' => $isStaff ? 1 : 0,
            'message' => $message,
            'created_at' => $now,
        ]);

        $messageId = $this->connection->lastInsertId();

        $this->setStatus($ticketId, $isStaff ? TicketStatus::ANSWERED : TicketStatus::OPEN);

        return new SupportMessage($messageId, $ticketId, $authorUserId, $isStaff, $message, $now);
    }

    public function close(int $ticketId, int $staffUserId, ?string $note): void
    {
        if ($note !== null && $note !== '') {
            $this->addMessage($ticketId, $staffUserId, true, $note);
        }

        $this->setStatus($ticketId, TicketStatus::CLOSED);
    }

    private function setStatus(int $ticketId, TicketStatus $status): void
    {
        $table = $this->connection->table('support_tickets');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d",
            [$status->value, $this->now(), $ticketId]
        );

        $this->connection->query($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findHeaderRow(int $id): ?array
    {
        $table = $this->connection->table('support_tickets');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);
        $rows = $this->connection->getResults($sql);

        return $rows[0] ?? null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<SupportTicket>
     */
    private function hydrateRows(array $rows): array
    {
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $messagesByTicket = $this->messagesForMany($ids);

        return array_map(
            fn (array $row): SupportTicket => $this->hydrateHeader($row, $messagesByTicket[(int) $row['id']] ?? []),
            $rows
        );
    }

    /**
     * @return list<SupportMessage>
     */
    private function messagesFor(int $ticketId): array
    {
        return $this->messagesForMany([$ticketId])[$ticketId] ?? [];
    }

    /**
     * @param list<int> $ticketIds
     * @return array<int, list<SupportMessage>>
     */
    private function messagesForMany(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $table = $this->connection->table('support_messages');
        $placeholders = implode(',', array_fill(0, count($ticketIds), '%d'));
        $sql = $this->connection->prepare(
            "SELECT * FROM {$table} WHERE ticket_id IN ({$placeholders}) ORDER BY id ASC",
            $ticketIds
        );

        $grouped = [];

        foreach ($this->connection->getResults($sql) as $row) {
            $message = $this->hydrateMessage($row);
            $grouped[$message->ticketId][] = $message;
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<SupportMessage> $messages
     */
    private function hydrateHeader(array $row, array $messages): SupportTicket
    {
        $branchId = isset($row['branch_id']) ? (int) $row['branch_id'] : 0;

        return new SupportTicket(
            (int) $row['id'],
            $branchId > 0 ? $branchId : null,
            (int) $row['created_by'],
            (string) $row['subject'],
            TicketStatus::from((string) $row['status']),
            (string) $row['created_at'],
            (string) $row['updated_at'],
            $messages
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateMessage(array $row): SupportMessage
    {
        return new SupportMessage(
            (int) $row['id'],
            (int) $row['ticket_id'],
            (int) $row['author_user_id'],
            ((int) $row['is_staff']) === 1,
            (string) $row['message'],
            (string) $row['created_at']
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
