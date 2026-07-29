<?php

declare(strict_types=1);

namespace Seviye\Pricing\Domain;

use InvalidArgumentException;

/**
 * A non-negative, 2-decimal TRY amount - the platform's storage precision
 * (scp_price_rules.price is DECIMAL(10,2)). Deliberately not a full
 * cents-based ledger type: that level of rigor belongs to Seviye Finance
 * once it exists, and building it here now for no current consumer would
 * be speculative.
 */
final class Money
{
    private function __construct(private readonly float $amount)
    {
    }

    public static function fromFloat(float $amount): self
    {
        if (!is_finite($amount) || $amount < 0.0) {
            $message = sprintf('Money amount must be a finite, non-negative number, got %s.', $amount);

            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new InvalidArgumentException($message);
        }

        return new self(round($amount, 2));
    }

    public function toFloat(): float
    {
        return $this->amount;
    }
}
