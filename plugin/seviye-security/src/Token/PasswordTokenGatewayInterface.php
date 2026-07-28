<?php

declare(strict_types=1);

namespace Seviye\Security\Token;

use DateTimeImmutable;

/**
 * Persists only the SHA-256 hash of a token, never the raw value - mirroring
 * how WordPress core stores its own password-reset keys. The raw token
 * exists only in the one-time link handed to the user.
 */
interface PasswordTokenGatewayInterface
{
    public function store(
        int $userId,
        string $tokenHash,
        PasswordTokenPurpose $purpose,
        DateTimeImmutable $expiresAt
    ): void;

    public function find(string $tokenHash): ?PasswordTokenRecord;

    public function consume(string $tokenHash): void;
}
