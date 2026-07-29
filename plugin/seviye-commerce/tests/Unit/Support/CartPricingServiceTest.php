<?php

declare(strict_types=1);

namespace Seviye\Commerce\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Commerce\Support\CartPricingService;
use Seviye\Commerce\Tests\Fakes\FakePriceResolver;
use Seviye\Commerce\Tests\Fakes\FakeStudentGuardianCheck;
use Seviye\Pricing\Contracts\PriceSource;
use Seviye\Pricing\Contracts\ResolvedPrice;

final class CartPricingServiceTest extends TestCase
{
    public function testIsValidGuardianDelegatesToTheGuardianCheckContract(): void
    {
        $guardianCheck = new FakeStudentGuardianCheck();
        $guardianCheck->put(42, 3);
        $service = new CartPricingService($guardianCheck, new FakePriceResolver());

        self::assertTrue($service->isValidGuardian(42, 3));
        self::assertFalse($service->isValidGuardian(42, 99));
        self::assertFalse($service->isValidGuardian(1, 3));
    }

    public function testResolvePriceForCartItemPassesStudentIdAndNoExplicitBranchId(): void
    {
        $priceResolver = new FakePriceResolver();
        $priceResolver->nextResult = new ResolvedPrice(89.90, PriceSource::STUDENT);
        $service = new CartPricingService(new FakeStudentGuardianCheck(), $priceResolver);

        $result = $service->resolvePriceForCartItem(3, 100, 149.90);

        self::assertSame(89.90, $result->amount);
        self::assertSame(PriceSource::STUDENT, $result->source);
        self::assertSame(
            ['productId' => 100, 'studentId' => 3, 'branchId' => null, 'fallbackPrice' => 149.90],
            $priceResolver->lastCall
        );
    }
}
