<?php

declare(strict_types=1);

namespace Seviye\Security\TwoFactor;

final class TwoFactorSecret
{
    public function __construct(
        public readonly int $userId,
        public readonly string $encryptedSecret,
        public readonly bool $confirmed
    ) {
    }
}
