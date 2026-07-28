<?php

declare(strict_types=1);

namespace Seviye\Branches\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Branches\Domain\BranchStatus;
use Seviye\Branches\Domain\CommissionRate;
use Seviye\Branches\Domain\Iban;
use Seviye\Branches\Repository\WpdbBranchRepository;
use Seviye\Branches\Tests\Fakes\FakeConnection;

final class WpdbBranchRepositoryTest extends TestCase
{
    private const VALID_IBAN = 'TR330006100519786457841326';

    /**
     * @return array<string, mixed>
     */
    private function branchRow(): array
    {
        return [
            'id' => '1',
            'name' => 'Kadıköy Şubesi',
            'slug' => 'kadikoy-subesi',
            'iban' => self::VALID_IBAN,
            'commission_rate' => '12.50',
            'phone' => '02161234567',
            'address' => 'Kadıköy, İstanbul',
            'status' => 'active',
        ];
    }

    public function testCreateInsertsAndReadsBackTheBranch(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->branchRow()];
        $repository = new WpdbBranchRepository($connection);

        $branch = $repository->create(
            'Kadıköy Şubesi',
            'kadikoy-subesi',
            Iban::fromString(self::VALID_IBAN),
            CommissionRate::fromPercentage(12.5),
            '02161234567',
            'Kadıköy, İstanbul'
        );

        self::assertSame(1, $branch->id);
        self::assertSame('Kadıköy Şubesi', $branch->name);
        self::assertSame(self::VALID_IBAN, $branch->iban?->value());
        self::assertSame(12.5, $branch->commissionRate->percentage());
        self::assertCount(1, $connection->inserted);
        self::assertSame('kadikoy-subesi', $connection->inserted[0][1]['slug']);
    }

    public function testCreateAllowsANullIbanAndPhoneAndAddress(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [[
            'id' => '2',
            'name' => 'Minimal Şube',
            'slug' => 'minimal-sube',
            'iban' => null,
            'commission_rate' => '0.00',
            'phone' => null,
            'address' => null,
            'status' => 'active',
        ]];
        $repository = new WpdbBranchRepository($connection);

        $branch = $repository->create(
            'Minimal Şube',
            'minimal-sube',
            null,
            CommissionRate::fromPercentage(0.0),
            null,
            null
        );

        self::assertNull($branch->iban);
        self::assertNull($branch->phone);
        self::assertNull($branch->address);
    }

    public function testFindReturnsNullWhenNoRowMatches(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbBranchRepository($connection);

        self::assertNull($repository->find(999));
    }

    public function testSlugExistsExcludesTheGivenId(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->branchRow()];
        $repository = new WpdbBranchRepository($connection);

        self::assertFalse($repository->slugExists('kadikoy-subesi', 1));
        self::assertTrue($repository->slugExists('kadikoy-subesi', 2));
        self::assertTrue($repository->slugExists('kadikoy-subesi'));
    }

    public function testAllHydratesEveryReturnedRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->branchRow(), [
            'id' => '2',
            'name' => 'İkinci Şube',
            'slug' => 'ikinci-sube',
            'iban' => null,
            'commission_rate' => '5.00',
            'phone' => null,
            'address' => null,
            'status' => 'inactive',
        ]];
        $repository = new WpdbBranchRepository($connection);

        $branches = $repository->all();

        self::assertCount(2, $branches);
        self::assertSame('Kadıköy Şubesi', $branches[0]->name);
        self::assertSame('İkinci Şube', $branches[1]->name);
    }

    public function testUpdatePersistsAndReadsBackTheBranch(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [array_merge($this->branchRow(), ['name' => 'Güncellenmiş Şube'])];
        $repository = new WpdbBranchRepository($connection);

        $branch = $repository->update(
            1,
            'Güncellenmiş Şube',
            Iban::fromString(self::VALID_IBAN),
            CommissionRate::fromPercentage(12.5),
            '02161234567',
            'Kadıköy, İstanbul',
            BranchStatus::ACTIVE
        );

        self::assertSame('Güncellenmiş Şube', $branch->name);
        self::assertCount(1, $connection->queries);
    }
}
