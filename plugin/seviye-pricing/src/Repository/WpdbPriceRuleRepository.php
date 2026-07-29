<?php

declare(strict_types=1);

namespace Seviye\Pricing\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Pricing\Domain\Money;
use Seviye\Pricing\Domain\PriceRule;
use Seviye\Pricing\Domain\PriceRuleStatus;
use Seviye\Pricing\Domain\PriceScope;

final class WpdbPriceRuleRepository implements PriceRuleRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(int $productId, PriceScope $scope, Money $price): PriceRule
    {
        $now = $this->now();

        $this->connection->insert($this->connection->table('price_rules'), [
            'product_id' => $productId,
            'student_id' => $scope->studentId,
            'branch_id' => $scope->branchId,
            'price' => $price->toFloat(),
            'status' => PriceRuleStatus::ACTIVE->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rule = $this->find($this->connection->lastInsertId());

        if ($rule === null) {
            throw new RuntimeException('Price rule could not be read back after insert.');
        }

        return $rule;
    }

    public function update(int $id, Money $price, PriceRuleStatus $status): PriceRule
    {
        $table = $this->connection->table('price_rules');
        $sql = $this->connection->prepare(
            'UPDATE ' . $table . ' SET price = %f, status = %s, updated_at = %s WHERE id = %d',
            [$price->toFloat(), $status->value, $this->now(), $id]
        );

        $this->connection->query($sql);

        $rule = $this->find($id);

        if ($rule === null) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new RuntimeException(sprintf('Price rule #%d could not be read back after update.', $id));
        }

        return $rule;
    }

    public function find(int $id): ?PriceRule
    {
        $table = $this->connection->table('price_rules');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function delete(int $id): void
    {
        $table = $this->connection->table('price_rules');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE id = %d", [$id]);

        $this->connection->query($sql);
    }

    public function activeRuleExists(int $productId, PriceScope $scope): bool
    {
        return $this->activeRuleFor($productId, $scope) !== null;
    }

    public function activeRuleFor(int $productId, PriceScope $scope): ?PriceRule
    {
        $table = $this->connection->table('price_rules');

        [$conditions, $params] = $this->scopeConditions($scope);
        $conditions = array_merge(['product_id = %d', 'status = %s'], $conditions);
        $params = array_merge([$productId, PriceRuleStatus::ACTIVE->value], $params);

        $sql = $this->connection->prepare(
            'SELECT * FROM ' . $table . ' WHERE ' . implode(' AND ', $conditions)
                . ' ORDER BY updated_at DESC, id DESC LIMIT 1',
            $params
        );

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function forProduct(int $productId): array
    {
        $table = $this->connection->table('price_rules');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE product_id = %d ORDER BY id ASC", [$productId]);

        $rows = $this->connection->getResults($sql);

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @return array{0: list<string>, 1: list<int>}
     */
    private function scopeConditions(PriceScope $scope): array
    {
        $conditions = [];
        $params = [];

        if ($scope->studentId !== null) {
            $conditions[] = 'student_id = %d';
            $params[] = $scope->studentId;
        } else {
            $conditions[] = 'student_id IS NULL';
        }

        if ($scope->branchId !== null) {
            $conditions[] = 'branch_id = %d';
            $params[] = $scope->branchId;
        } else {
            $conditions[] = 'branch_id IS NULL';
        }

        return [$conditions, $params];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PriceRule
    {
        $studentId = $row['student_id'] !== null ? (int) $row['student_id'] : null;
        $branchId = $row['branch_id'] !== null ? (int) $row['branch_id'] : null;

        $scope = match (true) {
            $studentId !== null => PriceScope::forStudent($studentId),
            $branchId !== null => PriceScope::forBranch($branchId),
            default => PriceScope::general(),
        };

        return new PriceRule(
            (int) $row['id'],
            (int) $row['product_id'],
            $scope,
            Money::fromFloat((float) $row['price']),
            PriceRuleStatus::from((string) $row['status'])
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
