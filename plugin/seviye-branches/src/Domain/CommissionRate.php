<?php

declare(strict_types=1);

namespace Seviye\Branches\Domain;

use InvalidArgumentException;

/**
 * A branch's commission percentage (0-100), used to compute its share of an
 * order at settlement time (Seviye Finance, once built).
 */
final class CommissionRate
{
    private const MIN = 0.0;
    private const MAX = 100.0;

    private function __construct(private readonly float $percentage)
    {
    }

    public static function fromPercentage(float $percentage): self
    {
        if ($percentage < self::MIN || $percentage > self::MAX) {
            $message = sprintf(
                'Commission rate must be between %.2f and %.2f, got %.2f.',
                self::MIN,
                self::MAX,
                $percentage
            );

            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new InvalidArgumentException($message);
        }

        return new self(round($percentage, 2));
    }

    public function percentage(): float
    {
        return $this->percentage;
    }

    /**
     * Fraction in [0, 1], for multiplying directly against an order total.
     */
    public function asFraction(): float
    {
        return $this->percentage / 100.0;
    }
}
