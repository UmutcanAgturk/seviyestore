<?php

declare(strict_types=1);

namespace Seviye\Depo\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Seviye\Depo\Domain\SupplierStatus;
use Seviye\Depo\Repository\WpdbSupplierRepository;
use Seviye\Depo\Tests\Fakes\FakeConnection;

final class WpdbSupplierRepositoryTest extends TestCase
{
    public function testCreateInsertsAndReadsBackTheSupplierByLastInsertId(): void
    {
        $connection = new FakeConnection();
        $connection->nextInsertId = 5;
        $connection->resultsToReturn = [$this->row(5, 'Okul Tekstil', 'active')];
        $repository = new WpdbSupplierRepository($connection);

        $supplier = $repository->create('Okul Tekstil', 'Ahmet Yılmaz', '5551112233', 'ahmet@example.com', '1234567890', 'İstanbul');

        self::assertSame(5, $supplier->id);
        self::assertSame('Okul Tekstil', $supplier->name);
        self::assertSame(SupplierStatus::ACTIVE, $supplier->status);

        [$table, $data] = $connection->inserted[0];
        self::assertSame('test_scp_suppliers', $table);
        self::assertSame('active', $data['status']);
    }

    public function testFindReturnsNullWhenNoRowMatches(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbSupplierRepository($connection);

        self::assertNull($repository->find(999));
    }

    public function testHydrateTreatsEmptyOptionalStringsAsNull(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row(1, 'Tedarikçi', 'passive', '')];
        $repository = new WpdbSupplierRepository($connection);

        $supplier = $repository->find(1);

        self::assertNotNull($supplier);
        self::assertNull($supplier->contactName);
        self::assertSame(SupplierStatus::PASSIVE, $supplier->status);
    }

    public function testDeleteRunsADeleteQueryForTheGivenId(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbSupplierRepository($connection);

        $repository->delete(1);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('DELETE FROM', $connection->queries[0]);
        self::assertStringContainsString('WHERE id = 1', $connection->queries[0]);
    }

    public function testDeleteThrowsWhenTheQueryFails(): void
    {
        $connection = new FakeConnection();
        $connection->queryShouldSucceed = false;
        $repository = new WpdbSupplierRepository($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tedarikçi #1 silinemedi:');

        $repository->delete(1);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id, string $name, string $status, ?string $contactName = 'Ahmet Yılmaz'): array
    {
        return [
            'id' => (string) $id,
            'name' => $name,
            'contact_name' => $contactName,
            'phone' => '5551112233',
            'email' => 'ahmet@example.com',
            'tax_number' => '1234567890',
            'address' => 'İstanbul',
            'status' => $status,
        ];
    }
}
