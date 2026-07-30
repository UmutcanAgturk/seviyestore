<?php

declare(strict_types=1);

namespace Seviye\Security\TwoFactor;

/**
 * RFC 6238 TOTP (HMAC-SHA1, 30-second step, 6 digits) - the standard every
 * mainstream authenticator app implements, so a secret provisioned here
 * works with any of them without this platform needing to ship its own app.
 */
final class Totp
{
    private const PERIOD_SECONDS = 30;
    private const DIGITS = 6;

    public static function generate(string $base32Secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD_SECONDS);

        return self::hotp(Base32::decode($base32Secret), $counter);
    }

    /**
     * Accepts a code from one step before/after "now" ($window steps each
     * side) to tolerate normal clock drift between the server and the
     * user's phone - the standard TOTP verification allowance.
     */
    public static function verify(string $base32Secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $secretBinary = Base32::decode($base32Secret);
        $counter = intdiv($timestamp ?? time(), self::PERIOD_SECONDS);

        for ($step = -$window; $step <= $window; $step++) {
            if (hash_equals(self::hotp($secretBinary, $counter + $step), $code)) {
                return true;
            }
        }

        return false;
    }

    private static function hotp(string $secretBinary, int $counter): string
    {
        $counterBytes = pack('N', 0) . pack('N', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $secretBinary, true);
        $offset = ord($hash[19]) & 0x0f;

        $truncated = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        $code = $truncated % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }
}
