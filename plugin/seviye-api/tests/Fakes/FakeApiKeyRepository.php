<?php

declare(strict_types=1);

namespace Seviye\Api\Tests\Fakes;

use Seviye\Api\Domain\ApiKey;
use Seviye\Api\Repository\ApiKeyRepositoryInterface;

final class FakeApiKeyRepository implements ApiKeyRepositoryInterface
{
    /** @var list<ApiKey> */
    public array $keys = [];

    /** @var list<int> */
    public array $touchedIds = [];

    public function create(int $userId, string $label, string $keyPrefix, string $keyHash): ApiKey
    {
        $apiKey = new ApiKey(
            count($this->keys) + 1,
            $userId,
            $label,
            $keyPrefix,
            $keyHash,
            '2026-07-30 12:00:00',
            null,
            null
        );

        $this->keys[] = $apiKey;

        return $apiKey;
    }

    public function findByHash(string $keyHash): ?ApiKey
    {
        foreach ($this->keys as $key) {
            if ($key->keyHash === $keyHash) {
                return $key;
            }
        }

        return null;
    }

    public function touchLastUsed(int $id): void
    {
        $this->touchedIds[] = $id;
    }

    public function revoke(int $id): void
    {
        $this->replace($id, static fn (ApiKey $k): ApiKey => new ApiKey(
            $k->id,
            $k->userId,
            $k->label,
            $k->keyPrefix,
            $k->keyHash,
            $k->createdAt,
            $k->lastUsedAt,
            '2026-07-30 12:00:01'
        ));
    }

    public function all(): array
    {
        return $this->keys;
    }

    /**
     * @param callable(ApiKey): ApiKey $replacer
     */
    private function replace(int $id, callable $replacer): void
    {
        foreach ($this->keys as $index => $key) {
            if ($key->id === $id) {
                $this->keys[$index] = $replacer($key);

                return;
            }
        }
    }
}
