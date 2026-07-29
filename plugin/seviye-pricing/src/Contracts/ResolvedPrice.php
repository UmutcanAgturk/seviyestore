<?php

declare(strict_types=1);

namespace Seviye\Pricing\Contracts;

final class ResolvedPrice
{
    public function __construct(
        public readonly float $amount,
        public readonly PriceSource $source
    ) {
    }
}
