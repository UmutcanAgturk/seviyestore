<?php

declare(strict_types=1);

namespace Seviye\Pricing\Tests\Unit\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Seviye\Pricing\Domain\PriceScope;
use Seviye\Pricing\Domain\PriceScopeType;

final class PriceScopeTest extends TestCase
{
    public function testGeneralHasNoTargets(): void
    {
        $scope = PriceScope::general();

        self::assertSame(PriceScopeType::GENERAL, $scope->type);
        self::assertNull($scope->branchId);
        self::assertNull($scope->studentId);
    }

    public function testForBranchSetsOnlyBranchId(): void
    {
        $scope = PriceScope::forBranch(7);

        self::assertSame(PriceScopeType::BRANCH, $scope->type);
        self::assertSame(7, $scope->branchId);
        self::assertNull($scope->studentId);
    }

    public function testForStudentSetsOnlyStudentId(): void
    {
        $scope = PriceScope::forStudent(3);

        self::assertSame(PriceScopeType::STUDENT, $scope->type);
        self::assertSame(3, $scope->studentId);
        self::assertNull($scope->branchId);
    }

    public function testForBranchRejectsNonPositiveId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PriceScope::forBranch(0);
    }

    public function testForStudentRejectsNonPositiveId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PriceScope::forStudent(-1);
    }
}
