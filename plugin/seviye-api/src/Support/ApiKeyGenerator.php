<?php

declare(strict_types=1);

namespace Seviye\Api\Support;

use Seviye\Api\Domain\GeneratedApiKey;

/**
 * Generates and hashes API keys. Hashed with plain SHA-256 - deliberately
 * NOT the slow, salted bcrypt-style hashing Security uses for passwords/TC
 * Kimlik No (see Seviye\Security\Auth\CredentialGatewayInterface): a
 * password is low-entropy, human-chosen, and must resist offline guessing,
 * so a slow hash is the whole point. An API key here is 192 bits of
 * `random_bytes()` output - already unguessable - so hashing exists only to
 * avoid storing the secret in plaintext, and a fast, deterministic digest
 * is what makes `WHERE key_hash = ?` a working O(1) lookup at all (a
 * bcrypt/Argon2 hash is salted and non-deterministic, so it cannot be
 * looked up this way). This is the same reasoning GitHub/Stripe-style
 * platform API keys use.
 */
final class ApiKeyGenerator
{
    private const PREFIX = 'scp_live_';
    private const PREFIX_VISIBLE_CHARS = 6;

    public static function generate(): GeneratedApiKey
    {
        $plainKey = self::PREFIX . bin2hex(random_bytes(24));

        return new GeneratedApiKey($plainKey, self::displayPrefix($plainKey), self::hash($plainKey));
    }

    public static function hash(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    public static function displayPrefix(string $plainKey): string
    {
        return substr($plainKey, 0, strlen(self::PREFIX) + self::PREFIX_VISIBLE_CHARS);
    }
}
