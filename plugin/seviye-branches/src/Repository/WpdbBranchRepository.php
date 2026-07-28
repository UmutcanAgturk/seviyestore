<?php

declare(strict_types=1);

namespace Seviye\Branches\Repository;

use RuntimeException;
use Seviye\Branches\Domain\Branch;
use Seviye\Branches\Domain\BranchStatus;
use Seviye\Branches\Domain\CommissionRate;
use Seviye\Branches\Domain\Iban;
use Seviye\Core\Database\ConnectionInterface;

final class WpdbBranchRepository implements BranchRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function create(
        string $name,
        string $slug,
        ?Iban $iban,
        CommissionRate $commissionRate,
        ?string $phone,
        ?string $address
    ): Branch {
        $now = $this->now();

        $this->connection->insert($this->connection->table('branches'), [
            'name' => $name,
            'slug' => $slug,
            'iban' => $iban?->value(),
            'commission_rate' => $commissionRate->percentage(),
            'phone' => $phone,
            'address' => $address,
            'status' => BranchStatus::ACTIVE->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // The unique slug constraint makes this read-back unambiguous
        // without needing a dedicated lastInsertId() port on ConnectionInterface.
        $branch = $this->findBySlug($slug);

        if ($branch === null) {
            throw new RuntimeException('Branch could not be read back after insert.');
        }

        return $branch;
    }

    public function update(
        int $id,
        string $name,
        ?Iban $iban,
        CommissionRate $commissionRate,
        ?string $phone,
        ?string $address,
        BranchStatus $status
    ): Branch {
        $table = $this->connection->table('branches');
        $sql = $this->connection->prepare(
            'UPDATE ' . $table . ' SET name = %s, iban = %s, commission_rate = %f, '
                . 'phone = %s, address = %s, status = %s, updated_at = %s WHERE id = %d',
            [$name, $iban?->value(), $commissionRate->percentage(), $phone, $address, $status->value, $this->now(), $id]
        );

        $this->connection->query($sql);

        $branch = $this->find($id);

        if ($branch === null) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
            throw new RuntimeException(sprintf('Branch #%d could not be read back after update.', $id));
        }

        return $branch;
    }

    public function find(int $id): ?Branch
    {
        $table = $this->connection->table('branches');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", [$id]);

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function findBySlug(string $slug): ?Branch
    {
        $table = $this->connection->table('branches');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE slug = %s LIMIT 1", [$slug]);

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function slugExists(string $slug, ?int $excludingId = null): bool
    {
        $existing = $this->findBySlug($slug);

        if ($existing === null) {
            return false;
        }

        return $excludingId === null || $existing->id !== $excludingId;
    }

    public function all(): array
    {
        $table = $this->connection->table('branches');
        $rows = $this->connection->getResults("SELECT * FROM {$table} ORDER BY name ASC");

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Branch
    {
        return new Branch(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['slug'],
            $this->nullableString($row['iban']) !== null ? Iban::fromString((string) $row['iban']) : null,
            CommissionRate::fromPercentage((float) $row['commission_rate']),
            $this->nullableString($row['phone']),
            $this->nullableString($row['address']),
            BranchStatus::from((string) $row['status'])
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
