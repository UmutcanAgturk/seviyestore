<?php

declare(strict_types=1);

namespace Seviye\Commerce\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Seviye\Commerce\Support\SplitPaymentCalculator;

final class SplitPaymentCalculatorTest extends TestCase
{
    public function testBranchShareIsTheCommissionPercentageOfPrice(): void
    {
        $calculator = new SplitPaymentCalculator();

        $split = $calculator->calculate(200.0, 12.5);

        self::assertSame(25.0, $split->branchShare);
        self::assertSame(175.0, $split->hqShare);
    }

    public function testSharesAddUpToTheOriginalPrice(): void
    {
        $calculator = new SplitPaymentCalculator();

        $split = $calculator->calculate(89.90, 33.33);

        self::assertEqualsWithDelta(89.90, $split->branchShare + $split->hqShare, 0.01);
    }

    public function testZeroCommissionMeansEverythingGoesToHq(): void
    {
        $calculator = new SplitPaymentCalculator();

        $split = $calculator->calculate(150.0, 0.0);

        self::assertSame(0.0, $split->branchShare);
        self::assertSame(150.0, $split->hqShare);
    }

    public function testFullCommissionMeansEverythingGoesToTheBranch(): void
    {
        $calculator = new SplitPaymentCalculator();

        $split = $calculator->calculate(150.0, 100.0);

        self::assertSame(150.0, $split->branchShare);
        self::assertSame(0.0, $split->hqShare);
    }
}
