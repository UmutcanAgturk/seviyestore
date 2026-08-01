<?php

declare(strict_types=1);

namespace Seviye\Depo\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Depo\Domain\PurchaseSuggestionStatus;
use Seviye\Depo\Repository\WpdbPurchaseSuggestionRepository;
use Seviye\Depo\Tests\Fakes\FakeConnection;

final class WpdbPurchaseSuggestionRepositoryTest extends TestCase
{
    public function testFindReturnsNullWhenNoRowMatches(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbPurchaseSuggestionRepository($connection);

        self::assertNull($repository->find(999));
    }

    public function testFindHydratesAPendingSuggestion(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row(1, 'pending', null, null)];
        $repository = new WpdbPurchaseSuggestionRepository($connection);

        $suggestion = $repository->find(1);

        self::assertNotNull($suggestion);
        self::assertSame(500, $suggestion->productId);
        self::assertSame(PurchaseSuggestionStatus::PENDING, $suggestion->status);
        self::assertNull($suggestion->convertedPurchaseOrderId);
    }

    public function testFindHydratesAConvertedSuggestionWithItsPurchaseOrderId(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row(1, 'converted', 'Düşük stok', '12')];
        $repository = new WpdbPurchaseSuggestionRepository($connection);

        $suggestion = $repository->find(1);

        self::assertNotNull($suggestion);
        self::assertSame(PurchaseSuggestionStatus::CONVERTED, $suggestion->status);
        self::assertSame(12, $suggestion->convertedPurchaseOrderId);
        self::assertSame('Düşük stok', $suggestion->reason);
    }

    public function testHasPendingReturnsTrueWhenCountIsPositive(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['total' => '1']];
        $repository = new WpdbPurchaseSuggestionRepository($connection);

        self::assertTrue($repository->hasPending(500));
    }

    public function testHasPendingReturnsFalseWhenCountIsZero(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [['total' => '0']];
        $repository = new WpdbPurchaseSuggestionRepository($connection);

        self::assertFalse($repository->hasPending(500));
    }

    public function testAllReturnsEmptyListWhenNoSuggestionsMatch(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbPurchaseSuggestionRepository($connection);

        self::assertSame([], $repository->all());
    }

    public function testDismissRunsAnUpdateQuerySettingStatusToDismissed(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbPurchaseSuggestionRepository($connection);

        $repository->dismiss(4);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('status = dismissed', $connection->queries[0]);
        self::assertStringContainsString('WHERE id = 4', $connection->queries[0]);
    }

    public function testConvertRunsAnUpdateQuerySettingStatusAndPurchaseOrderId(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbPurchaseSuggestionRepository($connection);

        $repository->convert(4, 12);

        self::assertCount(1, $connection->queries);
        self::assertStringContainsString('status = converted', $connection->queries[0]);
        self::assertStringContainsString('converted_purchase_order_id = 12', $connection->queries[0]);
        self::assertStringContainsString('WHERE id = 4', $connection->queries[0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id, string $status, ?string $reason, ?string $convertedPurchaseOrderId): array
    {
        return [
            'id' => (string) $id,
            'product_id' => '500',
            'suggested_quantity' => '10',
            'status' => $status,
            'reason' => $reason,
            'converted_purchase_order_id' => $convertedPurchaseOrderId,
            'created_at' => '2026-08-01 10:00:00',
        ];
    }
}
