<?php

declare(strict_types=1);

namespace Seviye\Depo\Repository;

use RuntimeException;
use Seviye\Core\Database\ConnectionInterface;
use Seviye\Depo\Domain\Supplier;
use Seviye\Depo\Domain\SupplierStatus;

final class WpdbSupplierRepository implements SupplierRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(
        string $name,
        ?string $contactName,
        ?string $phone,
        ?string $email,
        ?string $taxNumber,
        ?string $address,
        ?int $userId = null
    ): Supplier {
        $now = $this->now();

        $this->connection->insert($this->connection->table('suppliers'), [
            'name' => $name,
            'contact_name' => $contactName,
            'phone' => $phone,
            'email' => $email,
            'tax_number' => $taxNumber,
            'address' => $address,
            'status' => SupplierStatus::ACTIVE->value,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $supplier = $this->find($this->connection->lastInsertId());

        if ($supplier === null) {
            throw new RuntimeException('Tedarikçi eklendikten sonra okunamadı.');
        }

        return $supplier;
    }

    public function update(
        int $id,
        string $name,
        ?string $contactName,
        ?string $phone,
        ?string $email,
        ?string $taxNumber,
        ?string $address,
        SupplierStatus $status,
        ?int $userId = null
    ): Supplier {
        $table = $this->connection->table('suppliers');
        $sql = $this->connection->prepare(
            "UPDATE {$table} SET name = %s, contact_name = %s, phone = %s, email = %s, "
                . 'tax_number = %s, address = %s, status = %s, user_id = %d, updated_at = %s WHERE id = %d',
            [
                $name,
                (string) $contactName,
                (string) $phone,
                (string) $email,
                (string) $taxNumber,
                (string) $address,
                $status->value,
                $userId,
                $this->now(),
                $id,
            ]
        );

        $this->connection->query($sql);

        $supplier = $this->find($id);

        if ($supplier === null) {
            throw new RuntimeException('Tedarikçi güncellendikten sonra okunamadı.');
        }

        return $supplier;
    }

    public function delete(int $id): void
    {
        $table = $this->connection->table('suppliers');
        $sql = $this->connection->prepare("DELETE FROM {$table} WHERE id = %d", [$id]);

        if ($this->connection->query($sql)) {
            return;
        }

        $error = $this->lastDbError();

        if (stripos($error, 'foreign key constraint') !== false) {
            throw new RuntimeException('Bu tedarikçiye ait satın alma siparişleri olduğu için silinemiyor.');
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
        throw new RuntimeException(sprintf('Tedarikçi #%d silinemedi: %s', $id, $error));
    }

    public function find(int $id): ?Supplier
    {
        $table = $this->connection->table('suppliers');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);
        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function findByUserId(int $userId): ?Supplier
    {
        $table = $this->connection->table('suppliers');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", [$userId]);
        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function all(): array
    {
        $table = $this->connection->table('suppliers');
        $sql = "SELECT * FROM {$table} ORDER BY name ASC";

        return array_map($this->hydrate(...), $this->connection->getResults($sql));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Supplier
    {
        $userId = isset($row['user_id']) ? (int) $row['user_id'] : 0;

        return new Supplier(
            (int) $row['id'],
            (string) $row['name'],
            $this->nullableString($row['contact_name'] ?? null),
            $this->nullableString($row['phone'] ?? null),
            $this->nullableString($row['email'] ?? null),
            $this->nullableString($row['tax_number'] ?? null),
            $this->nullableString($row['address'] ?? null),
            SupplierStatus::from((string) $row['status']),
            $userId > 0 ? $userId : null
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }

    private function lastDbError(): string
    {
        global $wpdb;

        return isset($wpdb) && $wpdb->last_error !== '' ? $wpdb->last_error : 'bilinmeyen veritabanı hatası';
    }
}
