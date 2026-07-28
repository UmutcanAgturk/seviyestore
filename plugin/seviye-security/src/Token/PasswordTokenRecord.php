<?php

declare(strict_types=1);

namespace Seviye\Security\Token;

use DateTimeImmutable;

final class PasswordTokenRecord
{
    public function __construct(
        public readonly int $userId,
        public readonly PasswordTokenPurpose $purpose,
        public readonly DateTimeImmutable $expiresAt
    ) {
    }
}
