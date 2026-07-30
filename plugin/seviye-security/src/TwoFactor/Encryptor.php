<?php

declare(strict_types=1);

namespace Seviye\Security\TwoFactor;

use InvalidArgumentException;

/**
 * Symmetric encryption (libsodium secretbox: XSalsa20-Poly1305) for a TOTP
 * secret at rest - the one piece of data this platform stores that grants
 * account access if leaked, unlike everything else in scp_* tables.
 *
 * Deliberately takes its key as a plain constructor argument rather than
 * calling wp_salt() itself: that keeps this class fully unit-testable
 * (construct it with any 32-byte test key) the same way WpCredentialGateway
 * is kept out of AuthService's own tests. The one caller that needs the
 * real WordPress secret is {@see \Seviye\Security\SecurityModule::boot()},
 * via {@see fromSecret()}.
 */
final class Encryptor
{
    public function __construct(private readonly string $key)
    {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            $message = sprintf('Encryptor key must be exactly %d bytes.', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * Derives a fixed-length key from an arbitrary-length secret (e.g.
     * wp_salt()'s output) via SHA-256, since secretbox requires exactly
     * SODIUM_CRYPTO_SECRETBOX_KEYBYTES (32) bytes.
     */
    public static function fromSecret(string $secret): self
    {
        return new self(hash('sha256', $secret, true));
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding binary ciphertext for TEXT column storage, not code obfuscation.
        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Null on any failure (malformed input, wrong key, tampered ciphertext)
     * - callers treat a corrupt/undecryptable secret as "no secret", never
     * as a fatal error.
     */
    public function decrypt(string $encoded): ?string
    {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding stored ciphertext, not code obfuscation.
        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        return $plaintext === false ? null : $plaintext;
    }
}
