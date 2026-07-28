<?php

declare(strict_types=1);

namespace Seviye\Security\Auth;

/**
 * Minimum password strength rule enforced when a new password is set via
 * "Şifremi Unuttum" or "İlk Şifre Oluştur". Deliberately simple (length
 * only) rather than a full entropy scorer - a strength meter belongs in the
 * Theme's UI, not in this backend validation gate.
 */
final class PasswordPolicy
{
    private const MIN_LENGTH = 8;

    public static function isAcceptable(string $password): bool
    {
        return mb_strlen($password) >= self::MIN_LENGTH;
    }
}
