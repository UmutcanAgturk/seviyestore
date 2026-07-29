<?php

declare(strict_types=1);

namespace Seviye\Commerce\Tests\Fakes;

use Seviye\Pricing\Contracts\PriceResolverInterface;
use Seviye\Pricing\Contracts\PriceSource;
use Seviye\Pricing\Contracts\ResolvedPrice;

final class FakePriceResolver implements PriceResolverInterface
{
    /** @var array{productId: int, studentId: ?int, branchId: ?int, fallbackPrice: float}|null */
    public ?array $lastCall = null;

    public ResolvedPrice $nextResult;

    public function __construct()
    {
        $this->nextResult = new ResolvedPrice(0.0, PriceSource::FALLBACK);
    }

    public function resolve(int $productId, ?int $studentId, ?int $branchId, float $fallbackPrice): ResolvedPrice
    {
        $this->lastCall = [
            'productId' => $productId,
            'studentId' => $studentId,
            'branchId' => $branchId,
            'fallbackPrice' => $fallbackPrice,
        ];

        return $this->nextResult;
    }
}
