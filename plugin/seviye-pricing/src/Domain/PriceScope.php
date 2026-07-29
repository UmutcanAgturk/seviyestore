<?php

declare(strict_types=1);

namespace Seviye\Pricing\Domain;

use InvalidArgumentException;

/**
 * A price rule's target - self-validating, like Branches' Iban/CommissionRate:
 * exactly one of (branchId, studentId) is set, matching {@see type}. Named
 * constructors are the only way to build one, so an inconsistent
 * type/target combination can never exist.
 */
final class PriceScope
{
    private function __construct(
        public readonly PriceScopeType $type,
        public readonly ?int $branchId,
        public readonly ?int $studentId
    ) {
    }

    public static function general(): self
    {
        return new self(PriceScopeType::GENERAL, null, null);
    }

    public static function forBranch(int $branchId): self
    {
        if ($branchId <= 0) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new InvalidArgumentException(sprintf('Branch id must be positive, got %d.', $branchId));
        }

        return new self(PriceScopeType::BRANCH, $branchId, null);
    }

    public static function forStudent(int $studentId): self
    {
        if ($studentId <= 0) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new InvalidArgumentException(sprintf('Student id must be positive, got %d.', $studentId));
        }

        return new self(PriceScopeType::STUDENT, null, $studentId);
    }
}
