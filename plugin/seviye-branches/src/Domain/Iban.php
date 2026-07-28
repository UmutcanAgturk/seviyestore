<?php

declare(strict_types=1);

namespace Seviye\Branches\Domain;

use InvalidArgumentException;

/**
 * A validated IBAN (ISO 13616 structural format + mod-97 checksum), used
 * for a branch's payout account. Deliberately not Turkey-specific in the
 * checksum itself - the format/length constraints are the generic ISO
 * bounds - since nothing here depends on TR being the only country code.
 */
final class Iban
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        $normalized = self::normalize($raw);

        if (!self::isValid($normalized)) {
            throw new InvalidArgumentException('Value is not a valid IBAN.');
        }

        return new self($normalized);
    }

    public static function isValid(string $raw): bool
    {
        $normalized = self::normalize($raw);

        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $normalized)) {
            return false;
        }

        $rearranged = substr($normalized, 4) . substr($normalized, 0, 4);
        $numeric = '';

        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        return self::mod97($numeric) === 1;
    }

    public static function normalize(string $raw): string
    {
        return strtoupper(str_replace(' ', '', $raw));
    }

    private static function mod97(string $numeric): int
    {
        $remainder = $numeric;

        while (strlen($remainder) > 9) {
            $chunk = substr($remainder, 0, 9);
            $remainder = (string) ((int) $chunk % 97) . substr($remainder, 9);
        }

        return (int) $remainder % 97;
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
