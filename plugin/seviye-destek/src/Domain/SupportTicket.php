<?php

declare(strict_types=1);

namespace Seviye\Destek\Domain;

final class SupportTicket
{
    /**
     * @param list<SupportMessage> $messages
     */
    public function __construct(
        public readonly int $id,
        public readonly ?int $branchId,
        public readonly int $createdByUserId,
        public readonly string $subject,
        public readonly TicketStatus $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly array $messages
    ) {
    }
}
