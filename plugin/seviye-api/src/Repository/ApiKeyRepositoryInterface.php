<?php

declare(strict_types=1);

namespace Seviye\Api\Repository;

use Seviye\Api\Domain\ApiKey;

interface ApiKeyRepositoryInterface
{
    public function create(int $userId, string $label, string $keyPrefix, string $keyHash): ApiKey;

    /**
     * The only lookup the authentication path ever performs - by the
     * SHA-256 hash of the presented plain key, never by id/prefix (both of
     * which are unauthenticated, guessable identifiers).
     */
    public function findByHash(string $keyHash): ?ApiKey;

    public function touchLastUsed(int $id): void;

    public function revoke(int $id): void;

    /**
     * @return list<ApiKey>
     */
    public function all(): array;
}
