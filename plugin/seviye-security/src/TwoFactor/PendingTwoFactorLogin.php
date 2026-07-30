<?php

declare(strict_types=1);

namespace Seviye\Security\TwoFactor;

final class PendingTwoFactorLogin
{
    public function __construct(
        public readonly int $userId,
        public readonly bool $remember
    ) {
    }
}
