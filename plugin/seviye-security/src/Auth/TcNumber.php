<?php

declare(strict_types=1);

namespace Seviye\Security\Auth;

use InvalidArgumentException;

/**
 * A validated Turkish national ID number ("T.C. Kimlik No").
 *
 * Enforces the public 11-digit format and checksum algorithm (a Luhn-style
 * check digit scheme, not a lookup against any registry) so an invalid
 * number is rejected before it ever reaches a database query or an
 * authentication attempt.
 */
final class TcNumber
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException('Value is not a valid T.C. Kimlik No.');
        }

        return new self($value);
    }

    public static function isValid(string $value): bool
    {
        if (!preg_match('/^[0-9]{11}$/', $value)) {
            return false;
        }

        if ($value[0] === '0') {
            return false;
        }

        $digits = array_map('intval', str_split($value));

        $oddSum = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $evenSum = $digits[1] + $digits[3] + $digits[5] + $digits[7];

        $checkDigit10 = (($oddSum * 7) - $evenSum) % 10;

        if ($checkDigit10 < 0) {
            $checkDigit10 += 10;
        }

        if ($checkDigit10 !== $digits[9]) {
            return false;
        }

        $checkDigit11 = ($oddSum + $evenSum + $checkDigit10) % 10;

        return $checkDigit11 === $digits[10];
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
