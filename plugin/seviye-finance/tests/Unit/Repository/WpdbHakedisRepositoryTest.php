<?php

declare(strict_types=1);

namespace Seviye\Finance\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Finance\Domain\HakedisEntryType;
use Seviye\Finance\Repository\WpdbHakedisRepository;
use Seviye\Finance\Tests\Fakes\FakeConnection;

final class WpdbHakedisRepositoryTest extends TestCase
{
    public function testRecordInsertsAndReadsBackByLastInsertId(): void
    {
        $connection = new FakeConnection();
        $connection->nextInsertId = 5;
        $connection->resultsToReturn = [$this->row(5, 7, 500, 3, 42, '25.00', '12.50', '200.00', 'earned')];
        $repository = new WpdbHakedisRepository($connection);

        $entry = $repository->record(7, 500, 3, 42, 25.0, 12.5, 200.0, HakedisEntryType::EARNED);

        self::assertSame(5, $entry->id);
        self::assertSame(7, $entry->branchId);
        self::assertSame(25.0, $entry->amount);
        self::assertSame(HakedisEntryType::EARNED, $entry->type);

        [$table, $data] = $connection->inserted[0];
        self::assertSame('test_scp_hakedis_entries', $table);
        self::assertSame('earned', $data['type']);
        self::assertSame(25.0, $data['amount']);
    }

    public function testEntryExistsReflectsWhetherARowWasFound(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbHakedisRepository($connection);

        $connection->resultsToReturn = [['id' => '1']];
        self::assertTrue($repository->entryExists(500, 3, HakedisEntryType::EARNED));

        $connection->resultsToReturn = [];
        self::assertFalse($repository->entryExists(500, 3, HakedisEntryType::EARNED));
    }

    public function testBalanceForBranchSumsSignedAmounts(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['total' => '75.50']];
        $repository = new WpdbHakedisRepository($connection);

        self::assertSame(75.50, $repository->balanceForBranch(7));
    }

    public function testBalanceForBranchIsZeroWhenNoEntriesExist(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['total' => null]];
        $repository = new WpdbHakedisRepository($connection);

        self::assertSame(0.0, $repository->balanceForBranch(999));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        int $id,
        int $branchId,
        int $orderId,
        int $orderItemId,
        int $studentId,
        string $amount,
        string $commissionRate,
        string $price,
        string $type
    ): array {
        return [
            'id' => (string) $id,
            'branch_id' => (string) $branchId,
            'order_id' => (string) $orderId,
            'order_item_id' => (string) $orderItemId,
            'student_id' => (string) $studentId,
            'amount' => $amount,
            'commission_rate' => $commissionRate,
            'price' => $price,
            'type' => $type,
        ];
    }
}
