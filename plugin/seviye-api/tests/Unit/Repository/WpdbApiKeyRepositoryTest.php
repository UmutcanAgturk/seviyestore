<?php

declare(strict_types=1);

namespace Seviye\Api\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Seviye\Api\Repository\WpdbApiKeyRepository;
use Seviye\Api\Tests\Fakes\FakeConnection;

final class WpdbApiKeyRepositoryTest extends TestCase
{
    public function testCreateInsertsAndReadsBackByLastInsertId(): void
    {
        $connection = new FakeConnection();
        $connection->nextInsertId = 4;
        $connection->resultsToReturn = [$this->row(4, 12, 'Muhasebe Entegrasyonu', 'scp_live_ab12cd', str_repeat('a', 64))];
        $repository = new WpdbApiKeyRepository($connection);

        $apiKey = $repository->create(12, 'Muhasebe Entegrasyonu', 'scp_live_ab12cd', str_repeat('a', 64));

        self::assertSame(4, $apiKey->id);
        self::assertSame(12, $apiKey->userId);
        self::assertSame('Muhasebe Entegrasyonu', $apiKey->label);
        self::assertFalse($apiKey->isRevoked());

        [$table, $data] = $connection->inserted[0];
        self::assertSame('test_scp_api_keys', $table);
        self::assertSame(12, $data['user_id']);
    }

    public function testFindByHashReturnsNullWhenNoKeyMatches(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [];
        $repository = new WpdbApiKeyRepository($connection);

        self::assertNull($repository->findByHash(str_repeat('b', 64)));
    }

    public function testFindByHashReturnsTheMatchingKey(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [$this->row(4, 12, 'Mobil Uygulama', 'scp_live_ef34gh', str_repeat('c', 64))];
        $repository = new WpdbApiKeyRepository($connection);

        $apiKey = $repository->findByHash(str_repeat('c', 64));

        self::assertNotNull($apiKey);
        self::assertSame('Mobil Uygulama', $apiKey->label);
    }

    public function testTouchLastUsedUpdatesTheRow(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbApiKeyRepository($connection);

        $repository->touchLastUsed(4);

        self::assertStringContainsString('SET last_used_at', $connection->queries[0]);
        self::assertStringContainsString('WHERE id = 4', $connection->queries[0]);
    }

    public function testRevokeOnlyAffectsUnrevokedRows(): void
    {
        $connection = new FakeConnection();
        $repository = new WpdbApiKeyRepository($connection);

        $repository->revoke(4);

        self::assertStringContainsString('SET revoked_at', $connection->queries[0]);
        self::assertStringContainsString('revoked_at IS NULL', $connection->queries[0]);
    }

    public function testAllHydratesEveryRow(): void
    {
        $connection = new FakeConnection();
        $connection->resultsToReturn = [
            $this->row(2, 12, 'B', 'scp_live_bb', str_repeat('d', 64)),
            $this->row(1, 12, 'A', 'scp_live_aa', str_repeat('e', 64)),
        ];
        $repository = new WpdbApiKeyRepository($connection);

        $keys = $repository->all();

        self::assertCount(2, $keys);
        self::assertSame(2, $keys[0]->id);
        self::assertSame(1, $keys[1]->id);
    }

    public function testIsRevokedReflectsRevokedAt(): void
    {
        $connection = new FakeConnection();
        $row = $this->row(4, 12, 'X', 'scp_live_xx', str_repeat('f', 64));
        $row['revoked_at'] = '2026-07-30 12:00:00';
        $connection->resultsToReturn = [$row];
        $repository = new WpdbApiKeyRepository($connection);

        self::assertTrue($repository->findByHash(str_repeat('f', 64))->isRevoked());
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id, int $userId, string $label, string $keyPrefix, string $keyHash): array
    {
        return [
            'id' => (string) $id,
            'user_id' => (string) $userId,
            'label' => $label,
            'key_prefix' => $keyPrefix,
            'key_hash' => $keyHash,
            'created_at' => '2026-07-30 12:00:00',
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }
}
