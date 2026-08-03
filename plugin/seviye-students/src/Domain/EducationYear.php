<?php

declare(strict_types=1);

namespace Seviye\Students\Domain;

use InvalidArgumentException;

/**
 * A school year in "YYYY-YYYY" form (e.g. "2025-2026"), where the second
 * year must be exactly one more than the first.
 */
final class EducationYear
{
    private const MIN_START_YEAR = 2000;
    private const MAX_START_YEAR = 2100;

    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        if (!self::isValid($raw)) {
            throw new InvalidArgumentException('Value is not a valid education year (expected "YYYY-YYYY").');
        }

        return new self($raw);
    }

    public static function isValid(string $raw): bool
    {
        if (!preg_match('/^(\d{4})-(\d{4})$/', $raw, $matches)) {
            return false;
        }

        $start = (int) $matches[1];
        $end = (int) $matches[2];

        if ($end !== $start + 1) {
            return false;
        }

        return $start >= self::MIN_START_YEAR && $start <= self::MAX_START_YEAR;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function startYear(): int
    {
        return (int) explode('-', $this->value)[0];
    }

    /**
     * "Toplu sınıf/eğitim yılı geçişi" - the year immediately after this
     * one (e.g. "2025-2026" -> "2026-2027").
     */
    public function next(): self
    {
        $nextStart = $this->startYear() + 1;

        return new self($nextStart . '-' . ($nextStart + 1));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
