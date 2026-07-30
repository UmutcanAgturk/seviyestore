<?php

declare(strict_types=1);

namespace Seviye\Api\Auth;

/**
 * The outcome of one {@see ApiKeyAuthenticator::authenticate()} call - kept
 * separate from a plain nullable int return so THROTTLED and INVALID can be
 * told apart (the Http adapter maps them to different HTTP status codes:
 * 429 vs 401), the same reasoning
 * Seviye\Security\Auth\AuthResult uses for login attempts.
 */
final class ApiKeyAuthResult
{
    private function __construct(
        private readonly ?int $userId,
        private readonly bool $throttled
    ) {
    }

    public static function success(int $userId): self
    {
        return new self($userId, false);
    }

    public static function invalid(): self
    {
        return new self(null, false);
    }

    public static function throttled(): self
    {
        return new self(null, true);
    }

    public function isSuccessful(): bool
    {
        return $this->userId !== null;
    }

    public function isThrottled(): bool
    {
        return $this->throttled;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }
}
