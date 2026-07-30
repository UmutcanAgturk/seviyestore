<?php

declare(strict_types=1);

namespace Seviye\Api\Domain;

/**
 * One issued API key. The plain key itself is NEVER stored - only its
 * SHA-256 hash ({@see keyHash}); the platform has no way to display or
 * recover a key after creation, only to revoke it. `keyPrefix` (a short,
 * non-secret slice of the plain key, e.g. `scp_live_ab12cd`) is what the
 * management panel shows to help a Genel Merkez user recognize a key among
 * a list without ever exposing the secret itself.
 *
 * Managed exclusively by Genel Merkez ({@see \Seviye\Api\Rbac\ApiCapability::MANAGE_API_KEYS})
 * - a key's `userId` is whichever WordPress account (typically one holding
 * the `Sistem` role, provisioned via wp-admin for a specific external
 * integration) it authenticates requests as; it is not necessarily the
 * user who created it.
 */
final class ApiKey
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $label,
        public readonly string $keyPrefix,
        public readonly string $keyHash,
        public readonly string $createdAt,
        public readonly ?string $lastUsedAt,
        public readonly ?string $revokedAt
    ) {
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }
}
