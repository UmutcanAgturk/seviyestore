<?php

declare(strict_types=1);

namespace Seviye\Finance\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Finance\Domain\SettlementMethod;
use Seviye\Finance\Repository\WpdbSettlementRepository;
use Seviye\Finance\Tests\Fakes\FakeConnection;

final class WpdbSettlementRepositoryTest extends TestCase
{
    public function testRecordInsertsAndReadsBackByLastInsertId(): void
    {
        $connection = new FakeConnection();
        $connection->nextInsertId = 3;
        $connection->resultsToReturn = [$this->row(3, 7, '500.00', 'bank_transfer', 'Mart havalesi', 12)];
        $repository = new WpdbSettlementRepository($connection);

        $settlement = $repository->record(7, 500.0, SettlementMethod::BANK_TRANSFER, 'Mart havalesi', 12);

        self::assertSame(3, $settlement->id);
        self::assertSame(7, $settlement->branchId);
        self::assertSame(500.0, $settlement->amount);
        self::assertSame(SettlementMethod::BANK_TRANSFER, $settlement->method);
        self::assertSame('Mart havalesi', $settlement->note);
        self::assertSame(12, $settlement->recordedByUserId);

        [$table, $data] = $connection->inserted[0];
        self::assertSame('test_scp_hakedis_settlements', $table);
        self::assertSame(7, $data['branch_id']);
        self::assertSame('bank_transfer', $data['method']);
    }

    public function testRecordAllowsANullNote(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row(1, 7, '100.00', 'cash', null, 12)];
        $repository = new WpdbSettlementRepository($connection);

        $settlement = $repository->record(7, 100.0, SettlementMethod::CASH, null, 12);

        self::assertNull($settlement->note);
    }

    public function testListForBranchHydratesEveryRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [
            $this->row(2, 7, '100.00', 'cash', null, 12),
            $this->row(1, 7, '400.00', 'bank_transfer', null, 12),
        ];
        $repository = new WpdbSettlementRepository($connection);

        $settlements = $repository->listForBranch(7);

        self::assertCount(2, $settlements);
        self::assertSame(2, $settlements[0]->id);
        self::assertSame(1, $settlements[1]->id);
    }

    public function testSettledForBranchSumsAmounts(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['total' => '500.00']];
        $repository = new WpdbSettlementRepository($connection);

        self::assertSame(500.0, $repository->settledForBranch(7));
    }

    public function testSettledForBranchIsZeroWhenNoSettlementsExist(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['total' => null]];
        $repository = new WpdbSettlementRepository($connection);

        self::assertSame(0.0, $repository->settledForBranch(999));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        int $id,
        int $branchId,
        string $amount,
        string $method,
        ?string $note,
        int $recordedBy
    ): array {
        return [
            'id' => (string) $id,
            'branch_id' => (string) $branchId,
            'amount' => $amount,
            'method' => $method,
            'note' => $note,
            'recorded_by' => (string) $recordedBy,
            'created_at' => '2026-07-29 12:00:00',
        ];
    }
}
