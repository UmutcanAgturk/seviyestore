<?php

declare(strict_types=1);

namespace Seviye\Destek\Domain;

final class SupportMessage
{
    public function __construct(
        public readonly int $id,
        public readonly int $ticketId,
        public readonly int $authorUserId,
        public readonly bool $isStaff,
        public readonly string $message,
        public readonly string $createdAt
    ) {
    }
}
