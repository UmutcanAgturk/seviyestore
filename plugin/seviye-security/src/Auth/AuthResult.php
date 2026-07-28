<?php

declare(strict_types=1);

namespace Seviye\Security\Auth;

final class AuthResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly ?int $userId,
        public readonly ?AuthFailureReason $failureReason
    ) {
    }

    public static function success(int $userId): self
    {
        return new self(true, $userId, null);
    }

    public static function invalidCredentials(): self
    {
        return new self(false, null, AuthFailureReason::INVALID_CREDENTIALS);
    }

    public static function throttled(): self
    {
        return new self(false, null, AuthFailureReason::THROTTLED);
    }
}
