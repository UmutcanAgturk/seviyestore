<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Depo\Domain\PurchaseSuggestion;
use Seviye\Depo\Domain\PurchaseSuggestionStatus;

final class WpdbPurchaseSuggestionRepository implements PurchaseSuggestionRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(int $productId, int $suggestedQuantity, ?string $reason): PurchaseSuggestion
    {
        $now = $this->now();

        $this->connection->insert($this->connection->table('purchase_suggestions'), [
            'product_id' => $productId,
            'suggested_quantity' => $suggestedQuantity,
            'status' => PurchaseSuggestionStatus::PENDING->value,
            'reason' => $reason,
            'converted_purchase_order_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $suggestion = $this->find($this->connection->lastInsertId());

        if ($suggestion === null) {
            throw new RuntimeException('Satın alma önerisi eklendikten sonra okunamadı.');
        }

        return $suggestion;
    }

    public function hasPending(int $productId): bool
    {
        $table = $this->connection->table('purchase_suggestions');
        $sql = $this->connection->prepare(
            "SELECT COUNT(*) AS total FROM {$table} WHERE product_id = %d AND status = %s",
            [$productId, PurchaseSuggestionStatus::PENDING->value]
        );
        $rows = $this->connection->getResults($sql);

        return isset($rows[0]['total']) && (int) $rows[0]['total'] > 0;
    }

    public function find(int $id): ?PurchaseSuggestion
    {
        $table = $this->connection->table('purchase_suggestions');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);
        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function all(?PurchaseSuggestionStatus $status = null): array
    {
        $table = $this->connection->table('purchase_suggestions');

        if ($status !== null) {
            $sql = $this->connection->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC, id DESC",
                [$status->value]
            );
        } else {
            $sql = "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC";
        }

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    public function dismiss(int $id): void
    {
        $table = $this->connection->table('purchase_suggestions');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d",
            [PurchaseSuggestionStatus::DISMISSED->value, $this->now(), $id]
        );

        $this->connection->query($sql);
    }

    public function convert(int $id, int $purchaseOrderId): void
    {
        $table = $this->connection->table('purchase_suggestions');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET status = %s, converted_purchase_order_id = %d, updated_at = %s WHERE id = %d",
            [PurchaseSuggestionStatus::CONVERTED->value, $purchaseOrderId, $this->now(), $id]
        );

        $this->connection->query($sql);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PurchaseSuggestion
    {
        return new PurchaseSuggestion(
            (int) $row['id'],
            (int) $row['product_id'],
            (int) $row['suggested_quantity'],
            PurchaseSuggestionStatus::from((string) $row['status']),
            isset($row['reason']) && $row['reason'] !== '' && $row['reason'] !== null ? (string) $row['reason'] : null,
            isset($row['converted_purchase_order_id']) && $row['converted_purchase_order_id'] !== null
                ? (int) $row['converted_purchase_order_id']
                : null,
            (string) $row['created_at']
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
