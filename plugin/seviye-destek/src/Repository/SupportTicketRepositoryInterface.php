<?php

declare(strict_types=1);

namespace Seviye\Destek\Repository;

use Seviye\Destek\Domain\SupportMessage;
use Seviye\Destek\Domain\SupportTicket;

interface SupportTicketRepositoryInterface
{
    /**
     * Creates the ticket AND its opening message in one call - a ticket
     * with no messages is not a state this repository ever produces.
     */
    public function create(?int $branchId, int $createdByUserId, string $subject, string $message): SupportTicket;

    public function find(int $id): ?SupportTicket;

    /**
     * @return list<SupportTicket> newest-updated first
     */
    public function allForUser(int $userId): array;

    /**
     * $branchId null = every ticket, branch-scoped or not (Genel Merkez/
     * Bölge Müdürü); non-null = only that branch's own tickets (Şube
     * Müdürü/Rehberlik) - see Rbac\SupportCapability::MANAGE_TICKETS.
     *
     * @return list<SupportTicket> newest-updated first
     */
    public function allForBranch(?int $branchId): array;

    /**
     * Appends a message and recalculates status: a staff reply moves the
     * ticket to ANSWERED, a veli reply moves it (back) to OPEN - see
     * Domain\TicketStatus's docblock for the full state machine.
     */
    public function addMessage(int $ticketId, int $authorUserId, bool $isStaff, string $message): SupportMessage;

    /**
     * Optionally appends a final staff message, then sets status to
     * CLOSED regardless of what addMessage() alone would have computed.
     */
    public function close(int $ticketId, int $staffUserId, ?string $note): void;
}
