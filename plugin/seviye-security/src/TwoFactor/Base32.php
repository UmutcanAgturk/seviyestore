<?php

declare(strict_types=1);

namespace Seviye\Security\TwoFactor;

/**
 * RFC 4648 Base32 (no padding on output) - the encoding every TOTP
 * authenticator app (Google Authenticator, Authy, ...) expects a secret to
 * be shared in, and the wire format {@see Totp} accepts.
 */
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $binary): string
    {
        if ($binary === '') {
            return '';
        }

        $bits = '';

        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= self::ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    public static function decode(string $encoded): string
    {
        $encoded = strtoupper((string) preg_replace('/[^A-Za-z2-7]/', '', $encoded));
        $bits = '';

        foreach (str_split($encoded) as $char) {
            $position = strpos(self::ALPHABET, $char);

            if ($position === false) {
                continue;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) < 8) {
                break;
            }

            $binary .= chr((int) bindec($byte));
        }

        return $binary;
    }

    /**
     * 20 raw bytes (160 bits) - the same secret length Google Authenticator
     * and most TOTP apps default to.
     */
    public static function randomSecret(): string
    {
        return self::encode(random_bytes(20));
    }
}
