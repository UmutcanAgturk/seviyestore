<?php

declare(strict_types=1);

namespace Seviye\Pricing\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Pricing\Domain\Money;
use Seviye\Pricing\Domain\PriceRuleStatus;
use Seviye\Pricing\Domain\PriceScope;
use Seviye\Pricing\Repository\WpdbPriceRuleRepository;
use Seviye\Pricing\Tests\Fakes\FakeConnection;

final class WpdbPriceRuleRepositoryTest extends TestCase
{
    public function testCreateInsertsAndReadsBackByLastInsertId(): void
    {
        $connection = new FakeConnection();
        $connection->nextInsertId = 42;
        $connection->resultsToReturn = [$this->row(42, 100, null, 7, '250.00', 'active')];
        $repository = new WpdbPriceRuleRepository($connection);

        $rule = $repository->create(100, PriceScope::forBranch(7), Money::fromFloat(250.0));

        self::assertSame(42, $rule->id);
        self::assertSame(100, $rule->productId);
        self::assertSame(7, $rule->scope->branchId);
        self::assertSame(250.0, $rule->price->toFloat());
        self::assertSame(PriceRuleStatus::ACTIVE, $rule->status);

        [$table, $data] = $connection->inserted[0];
        self::assertSame('test_scp_price_rules', $table);
        self::assertSame(100, $data['product_id']);
        self::assertNull($data['student_id']);
        self::assertSame(7, $data['branch_id']);
    }

    public function testUpdateWritesThenReadsBack(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row(1, 100, null, null, '300.00', 'inactive')];
        $repository = new WpdbPriceRuleRepository($connection);

        $rule = $repository->update(1, Money::fromFloat(300.0), PriceRuleStatus::INACTIVE);

        self::assertSame(300.0, $rule->price->toFloat());
        self::assertSame(PriceRuleStatus::INACTIVE, $rule->status);
        self::assertCount(1, $connection->queries);
    }

    public function testFindReturnsNullWhenNoRowMatches(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbPriceRuleRepository($connection);

        self::assertNull($repository->find(999));
    }

    public function testActiveRuleForBuildsIsNullConditionsForUnusedScopeTargets(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row(5, 100, null, null, '199.00', 'active')];
        $repository = new WpdbPriceRuleRepository($connection);

        $rule = $repository->activeRuleFor(100, PriceScope::general());

        self::assertNotNull($rule);
        self::assertSame(5, $rule->id);
    }

    public function testActiveRuleExistsReflectsWhetherARuleWasFound(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbPriceRuleRepository($connection);

        $connection->resultsToReturn = [$this->row(5, 100, 3, null, '199.00', 'active')];
        self::assertTrue($repository->activeRuleExists(100, PriceScope::forStudent(3)));

        $connection->resultsToReturn = [];
        self::assertFalse($repository->activeRuleExists(100, PriceScope::forStudent(3)));
    }

    public function testForProductHydratesEveryRowRegardlessOfScope(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [
            $this->row(1, 100, null, null, '100.00', 'active'),
            $this->row(2, 100, null, 7, '90.00', 'active'),
            $this->row(3, 100, 3, null, '80.00', 'active'),
        ];
        $repository = new WpdbPriceRuleRepository($connection);

        $rules = $repository->forProduct(100);

        self::assertCount(3, $rules);
        self::assertNull($rules[0]->scope->branchId);
        self::assertSame(7, $rules[1]->scope->branchId);
        self::assertSame(3, $rules[2]->scope->studentId);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id, int $productId, ?int $studentId, ?int $branchId, string $price, string $status): array
    {
        return [
            'id' => (string) $id,
            'product_id' => (string) $productId,
            'student_id' => $studentId !== null ? (string) $studentId : null,
            'branch_id' => $branchId !== null ? (string) $branchId : null,
            'price' => $price,
            'status' => $status,
        ];
    }
}
